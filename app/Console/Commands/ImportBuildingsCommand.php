<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Building;
use App\Models\Dataset;
use App\Models\AuditLog;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Exception;

class ImportBuildingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:buildings 
                            {dataset_id : The ID of the dataset to associate with imported buildings}
                            {file_path : Path to the CSV or GeoJSON file containing building data}
                            {--format=auto : File format (csv, geojson, or auto)}
                            {--batch-size=100 : Number of records to process in each batch}
                            {--update : Update existing buildings instead of creating new ones}
                            {--dry-run : Validate data without actually importing}
                            {--limit=0 : Maximum number of records to import (0 for no limit)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import building data from CSV or GeoJSON files into the buildings table with PostGIS geometry support';

    /**
     * Statistics tracking
     */
    private array $stats = [
        'processed' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    private array $validationErrors = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $datasetId = $this->argument('dataset_id');
        $filePath = $this->argument('file_path');
        $format = $this->option('format');
        $batchSize = (int) $this->option('batch-size');
        $updateMode = $this->option('update');
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->info("🚀 Starting building data import process...");

        // Validate dataset exists
        $dataset = Dataset::find($datasetId);
        if (!$dataset) {
            $this->error("❌ Dataset with ID {$datasetId} not found!");
            return Command::FAILURE;
        }

        $this->info("📊 Dataset: {$dataset->name} (ID: {$dataset->id})");

        // Validate file exists
        if (!file_exists($filePath)) {
            $this->error("❌ File not found: {$filePath}");
            return Command::FAILURE;
        }

        // Determine file format
        $detectedFormat = $this->detectFileFormat($filePath, $format);
        if (!$detectedFormat) {
            $this->error("❌ Could not determine file format. Supported formats: CSV, GeoJSON");
            return Command::FAILURE;
        }

        $this->info("📁 File format: " . strtoupper($detectedFormat));
        $this->info("📏 Batch size: {$batchSize}");
        $this->info("🔄 Mode: " . ($updateMode ? 'Update' : 'Create'));
        if ($limit > 0) {
            $this->info("🔢 Import limit: {$limit} records");
        }

        if ($dryRun) {
            $this->warn("🧪 DRY RUN MODE - No data will be actually imported");
        }

        $this->newLine();

        try {
            // Process file based on format
            if ($detectedFormat === 'csv') {
                $result = $this->processCsvFile($filePath, $dataset, $batchSize, $updateMode, $dryRun);
            } elseif ($detectedFormat === 'geojson') {
                $result = $this->processGeoJsonFile($filePath, $dataset, $batchSize, $updateMode, $dryRun);
            }

            // Display results
            $this->displayResults($dataset, $filePath, $dryRun);

            // Log the import activity
            if (!$dryRun) {
                $this->logImportActivity($dataset, $filePath, $detectedFormat);
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("❌ Import failed: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * Detect file format based on extension or content
     */
    private function detectFileFormat(string $filePath, string $format): ?string
    {
        if ($format !== 'auto') {
            return $format;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'csv':
                return 'csv';
            case 'json':
            case 'geojson':
                return 'geojson';
            default:
                // Try to detect by content
                $sample = file_get_contents($filePath, false, null, 0, 1000);
                if (str_contains($sample, '"type":"FeatureCollection"') || str_contains($sample, '"type": "FeatureCollection"')) {
                    return 'geojson';
                }
                return null;
        }
    }

    /**
     * Process CSV file
     */
    private function processCsvFile(string $filePath, Dataset $dataset, int $batchSize, bool $updateMode, bool $dryRun): bool
    {
        $this->info("📄 Processing CSV file...");

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception("Could not open CSV file: {$filePath}");
        }

        // Read header
        $header = fgetcsv($handle);
        if (!$header) {
            throw new Exception("Could not read CSV header");
        }

        $this->info("📋 CSV columns: " . implode(', ', $header));

        // Validate required columns (TLI is now optional)
        $requiredColumns = ['gid', 'geometry', 'building_type_classification'];
        $missingColumns = array_diff($requiredColumns, $header);
        if (!empty($missingColumns)) {
            throw new Exception("Missing required columns: " . implode(', ', $missingColumns));
        }

        $batch = [];
        $rowNumber = 1; // Header is row 0
        $limit = (int) $this->option('limit');

        while (($row = fgetcsv($handle)) !== false) {
            if ($limit > 0 && $this->stats['processed'] >= $limit) {
                $this->info("Reached import limit of {$limit} records. Stopping CSV processing.");
                break;
            }
            $rowNumber++;
            $this->stats['processed']++;

            // Create associative array
            $data = array_combine($header, $row);

            // Validate and process row
            $processedData = $this->validateAndProcessRow($data, $rowNumber);
            if ($processedData) {
                $processedData['dataset_id'] = $dataset->id;
                $batch[] = $processedData;

                // Process batch when it reaches the specified size
                if (count($batch) >= $batchSize) {
                    $this->processBatch($batch, $updateMode, $dryRun);
                    $batch = [];

                    // Show progress
                    $this->info("✅ Processed {$this->stats['processed']} records...");
                }
            }
        }

        // Process remaining batch
        if (!empty($batch)) {
            $this->processBatch($batch, $updateMode, $dryRun);
        }

        fclose($handle);
        return true;
    }

    /**
     * Process GeoJSON file
     */
    private function processGeoJsonFile(string $filePath, Dataset $dataset, int $batchSize, bool $updateMode, bool $dryRun): bool
    {
        $this->info("🗺️ Processing GeoJSON file...");

        $content = file_get_contents($filePath);
        $geoJson = json_decode($content, true);

        if (!$geoJson) {
            throw new Exception("Invalid JSON in file: {$filePath}");
        }

        if (!isset($geoJson['type']) || $geoJson['type'] !== 'FeatureCollection') {
            throw new Exception("GeoJSON must be a FeatureCollection");
        }

        if (!isset($geoJson['features']) || !is_array($geoJson['features'])) {
            throw new Exception("GeoJSON FeatureCollection must contain features array");
        }

        $this->info("📊 Features found: " . count($geoJson['features']));

        $batch = [];
        $featureIndex = 0;
        $limit = (int) $this->option('limit');

        foreach ($geoJson['features'] as $feature) {
            if ($limit > 0 && $this->stats['processed'] >= $limit) {
                $this->info("Reached import limit of {$limit} records. Stopping GeoJSON processing.");
                break;
            }
            $featureIndex++;
            $this->stats['processed']++;

            // Process GeoJSON feature
            $processedData = $this->processGeoJsonFeature($feature, $featureIndex);
            if ($processedData) {
                $processedData['dataset_id'] = $dataset->id;
                $batch[] = $processedData;

                // Process batch when it reaches the specified size
                if (count($batch) >= $batchSize) {
                    $this->processBatch($batch, $updateMode, $dryRun);
                    $batch = [];

                    // Show progress
                    $this->info("✅ Processed {$this->stats['processed']} features...");
                }
            }
        }

        // Process remaining batch
        if (!empty($batch)) {
            $this->processBatch($batch, $updateMode, $dryRun);
        }

        return true;
    }

    /**
     * Validate and process a row of data for insertion
     */
    private function validateAndProcessRow(array $data, int $rowNumber): ?array
    {
        try {
            // Basic validation (TLI is now optional for backward compatibility)
            $validator = Validator::make($data, [
                'gid' => 'required|string|max:255',
                'thermal_loss_index_tli' => 'nullable|numeric|min:0|max:100',
                'building_type_classification' => 'required|string|max:100',
                'geometry' => 'required|string',
                // New anomaly detection fields (all optional)
                'average_heatloss' => 'nullable|numeric',
                'reference_heatloss' => 'nullable|numeric',
                'heatloss_difference' => 'nullable|numeric',
                'abs_heatloss_difference' => 'nullable|numeric',
                'threshold' => 'nullable|numeric',
                'is_anomaly' => 'nullable|boolean',
                'confidence' => 'nullable|numeric|min:0|max:1',
                'building_id' => 'nullable|string|max:255', // For anomaly CSV imports
            ]);

            if ($validator->fails()) {
                $this->validationErrors[] = "Row {$rowNumber}: " . implode(', ', $validator->errors()->all());
                $this->stats['errors']++;
                return null;
            }

            // Convert geometry
            $geometry = $this->parseGeometry($data['geometry'], $rowNumber);
            if (!$geometry) {
                return null;
            }

            // Use building_id if provided, otherwise use gid
            $gid = isset($data['building_id']) && !empty($data['building_id']) ? $data['building_id'] : $data['gid'];

            // Prepare data for insertion
            return [
                'gid' => trim($gid),
                'geometry' => $geometry,
                'thermal_loss_index_tli' => isset($data['thermal_loss_index_tli']) && $data['thermal_loss_index_tli'] !== '' ? (int) $data['thermal_loss_index_tli'] : null,
                'building_type_classification' => trim($data['building_type_classification']),
                'co2_savings_estimate' => isset($data['co2_savings_estimate']) ? (float) $data['co2_savings_estimate'] : null,
                'address' => isset($data['address']) ? trim($data['address']) : null,
                'owner_operator_details' => isset($data['owner_operator_details']) ? trim($data['owner_operator_details']) : null,
                'cadastral_reference' => isset($data['cadastral_reference']) ? trim($data['cadastral_reference']) : null,
                'before_renovation_tli' => isset($data['before_renovation_tli']) ? (int) $data['before_renovation_tli'] : null,
                'after_renovation_tli' => isset($data['after_renovation_tli']) ? (int) $data['after_renovation_tli'] : null,
                'last_analyzed_at' => isset($data['thermal_loss_index_tli']) && $data['thermal_loss_index_tli'] !== '' ? now() : null,
                // New anomaly detection fields
                'average_heatloss' => isset($data['average_heatloss']) && $data['average_heatloss'] !== '' ? (float) $data['average_heatloss'] : null,
                'reference_heatloss' => isset($data['reference_heatloss']) && $data['reference_heatloss'] !== '' ? (float) $data['reference_heatloss'] : null,
                'heatloss_difference' => isset($data['heatloss_difference']) && $data['heatloss_difference'] !== '' ? (float) $data['heatloss_difference'] : null,
                'abs_heatloss_difference' => isset($data['abs_heatloss_difference']) && $data['abs_heatloss_difference'] !== '' ? (float) $data['abs_heatloss_difference'] : null,
                'threshold' => isset($data['threshold']) && $data['threshold'] !== '' ? (float) $data['threshold'] : null,
                'is_anomaly' => isset($data['is_anomaly']) && $data['is_anomaly'] !== '' ? filter_var($data['is_anomaly'], FILTER_VALIDATE_BOOLEAN) : false,
                'confidence' => isset($data['confidence']) && $data['confidence'] !== '' ? (float) $data['confidence'] : null,
            ];
        } catch (Exception $e) {
            $this->validationErrors[] = "Row {$rowNumber}: " . $e->getMessage();
            $this->stats['errors']++;
            return null;
        }
    }

    /**
     * Process a single GeoJSON feature
     */
    private function processGeoJsonFeature(array $feature, int $featureIndex): ?array
    {
        try {
            // Validate feature structure
            if (!isset($feature['type']) || $feature['type'] !== 'Feature') {
                $this->validationErrors[] = "Feature {$featureIndex}: Not a valid Feature";
                $this->stats['errors']++;
                return null;
            }

            if (!isset($feature['properties']) || !isset($feature['geometry'])) {
                $this->validationErrors[] = "Feature {$featureIndex}: Missing properties or geometry";
                $this->stats['errors']++;
                return null;
            }

            $properties = $feature['properties'];
            $geometry = $feature['geometry'];

            // Validate required properties (TLI is now optional)
            $validator = Validator::make($properties, [
                'gid' => 'required|string|max:255',
                'thermal_loss_index_tli' => 'nullable|numeric|min:0|max:100',
                'building_type_classification' => 'required|string|max:100',
                // New anomaly detection fields (all optional)
                'average_heatloss' => 'nullable|numeric',
                'reference_heatloss' => 'nullable|numeric',
                'heatloss_difference' => 'nullable|numeric',
                'abs_heatloss_difference' => 'nullable|numeric',
                'threshold' => 'nullable|numeric',
                'is_anomaly' => 'nullable|boolean',
                'confidence' => 'nullable|numeric|min:0|max:1',
                'building_id' => 'nullable|string|max:255', // For anomaly CSV imports
            ]);

            if ($validator->fails()) {
                $this->validationErrors[] = "Feature {$featureIndex}: " . implode(', ', $validator->errors()->all());
                $this->stats['errors']++;
                return null;
            }

            // Convert geometry
            $spatialGeometry = $this->parseGeoJsonGeometry($geometry, $featureIndex);
            if (!$spatialGeometry) {
                return null;
            }

            // Use building_id if provided, otherwise use gid
            $gid = isset($properties['building_id']) && !empty($properties['building_id']) ? $properties['building_id'] : $properties['gid'];

            // Prepare data for insertion
            return [
                'gid' => trim($gid),
                'geometry' => $spatialGeometry,
                'thermal_loss_index_tli' => isset($properties['thermal_loss_index_tli']) && $properties['thermal_loss_index_tli'] !== '' ? (int) $properties['thermal_loss_index_tli'] : null,
                'building_type_classification' => trim($properties['building_type_classification']),
                'co2_savings_estimate' => isset($properties['co2_savings_estimate']) ? (float) $properties['co2_savings_estimate'] : null,
                'address' => isset($properties['address']) ? trim($properties['address']) : null,
                'owner_operator_details' => isset($properties['owner_operator_details']) ? trim($properties['owner_operator_details']) : null,
                'cadastral_reference' => isset($properties['cadastral_reference']) ? trim($properties['cadastral_reference']) : null,
                'before_renovation_tli' => isset($properties['before_renovation_tli']) ? (int) $properties['before_renovation_tli'] : null,
                'after_renovation_tli' => isset($properties['after_renovation_tli']) ? (int) $properties['after_renovation_tli'] : null,
                'last_analyzed_at' => isset($properties['thermal_loss_index_tli']) && $properties['thermal_loss_index_tli'] !== '' ? now() : null,
                // New anomaly detection fields
                'average_heatloss' => isset($properties['average_heatloss']) && $properties['average_heatloss'] !== '' ? (float) $properties['average_heatloss'] : null,
                'reference_heatloss' => isset($properties['reference_heatloss']) && $properties['reference_heatloss'] !== '' ? (float) $properties['reference_heatloss'] : null,
                'heatloss_difference' => isset($properties['heatloss_difference']) && $properties['heatloss_difference'] !== '' ? (float) $properties['heatloss_difference'] : null,
                'abs_heatloss_difference' => isset($properties['abs_heatloss_difference']) && $properties['abs_heatloss_difference'] !== '' ? (float) $properties['abs_heatloss_difference'] : null,
                'threshold' => isset($properties['threshold']) && $properties['threshold'] !== '' ? (float) $properties['threshold'] : null,
                'is_anomaly' => isset($properties['is_anomaly']) && $properties['is_anomaly'] !== '' ? filter_var($properties['is_anomaly'], FILTER_VALIDATE_BOOLEAN) : false,
                'confidence' => isset($properties['confidence']) && $properties['confidence'] !== '' ? (float) $properties['confidence'] : null,
            ];
        } catch (Exception $e) {
            $this->validationErrors[] = "Feature {$featureIndex}: " . $e->getMessage();
            $this->stats['errors']++;
            return null;
        }
    }

    /**
     * Parse geometry from various formats (WKT, GeoJSON string)
     */
    private function parseGeometry(string $geometryString, int $rowNumber): ?Polygon
    {
        try {
            // Try to parse as JSON first (GeoJSON geometry)
            $decoded = json_decode($geometryString, true);
            if ($decoded) {
                return $this->parseGeoJsonGeometry($decoded, $rowNumber);
            }

            // Handle MULTIPOLYGON Z format - convert to simple POLYGON
            if (str_starts_with(strtoupper(trim($geometryString)), 'MULTIPOLYGON')) {
                $geometryString = $this->convertMultiPolygonToPolygon($geometryString, $rowNumber);
                if (!$geometryString) {
                    return null;
                }
            }

            // Try to parse as WKT
            if (str_starts_with(strtoupper(trim($geometryString)), 'POLYGON')) {
                return Factory::parse($geometryString);
            }

            throw new Exception("Unknown geometry format");
        } catch (Exception $e) {
            $this->validationErrors[] = "Row {$rowNumber}: Invalid geometry - " . $e->getMessage();
            $this->stats['errors']++;
            return null;
        }
    }

    /**
     * Convert MULTIPOLYGON Z to simple POLYGON by taking the first polygon and removing Z coordinates
     */
    private function convertMultiPolygonToPolygon(string $multiPolygonString, int $rowNumber): ?string
    {
        try {
            // Remove "MULTIPOLYGON Z " and extract the first polygon
            $cleaned = trim($multiPolygonString);
            
            // Remove MULTIPOLYGON Z prefix
            if (preg_match('/^MULTIPOLYGON\s*Z?\s*\(\(\((.*?)\)\)\)/i', $cleaned, $matches)) {
                $coordinates = $matches[1];
                
                // Remove Z coordinates (third number in each coordinate triplet)
                $coordinates = preg_replace('/(\d+\.?\d*)\s+(\d+\.?\d*)\s+\d+\.?\d*/', '$1 $2', $coordinates);
                
                return "POLYGON(({$coordinates}))";
            }
            
            throw new Exception("Could not parse MULTIPOLYGON format");
        } catch (Exception $e) {
            $this->validationErrors[] = "Row {$rowNumber}: MULTIPOLYGON conversion failed - " . $e->getMessage();
            $this->stats['errors']++;
            return null;
        }
    }

    /**
     * Parse GeoJSON geometry object
     */
    private function parseGeoJsonGeometry(array $geometry, int $identifier): ?Polygon
    {
        try {
            if (!isset($geometry['type']) || $geometry['type'] !== 'Polygon') {
                throw new Exception("Only Polygon geometry is supported, got: " . ($geometry['type'] ?? 'unknown'));
            }

            if (!isset($geometry['coordinates']) || !is_array($geometry['coordinates'])) {
                throw new Exception("Missing or invalid coordinates");
            }

            $coordinates = $geometry['coordinates'];

            // GeoJSON Polygon coordinates are [[[lon, lat], [lon, lat], ...]]
            if (!isset($coordinates[0]) || !is_array($coordinates[0])) {
                throw new Exception("Invalid polygon coordinates structure");
            }

            $ring = $coordinates[0]; // Exterior ring
            $points = [];

            foreach ($ring as $coord) {
                if (!is_array($coord) || count($coord) < 2) {
                    throw new Exception("Invalid coordinate pair");
                }

                // GeoJSON uses [longitude, latitude] order
                $points[] = new Point($coord[1], $coord[0]); // Create Point(lat, lon)
            }

            return new Polygon([new LineString($points)]);
        } catch (Exception $e) {
            $this->validationErrors[] = "Item {$identifier}: Invalid GeoJSON geometry - " . $e->getMessage();
            $this->stats['errors']++;
            return null;
        }
    }

    /**
     * Process a batch of records
     */
    private function processBatch(array $batch, bool $updateMode, bool $dryRun): void
    {
        if ($dryRun) {
            $this->stats['created'] += count($batch);
            return;
        }

        try {
            DB::transaction(function () use ($batch, $updateMode) {
                foreach ($batch as $record) {
                    if ($updateMode) {
                        // Update or create
                        $building = Building::updateOrCreate(
                            ['gid' => $record['gid']],
                            $record
                        );

                        if ($building->wasRecentlyCreated) {
                            $this->stats['created']++;
                        } else {
                            $this->stats['updated']++;
                        }
                    } else {
                        // Create only - skip if exists
                        if (Building::where('gid', $record['gid'])->exists()) {
                            $this->stats['skipped']++;
                        } else {
                            Building::create($record);
                            $this->stats['created']++;
                        }
                    }
                }
            });
        } catch (Exception $e) {
            $this->error("❌ Batch processing failed: " . $e->getMessage());
            $this->stats['errors'] += count($batch);
        }
    }

    /**
     * Display import results
     */
    private function displayResults(Dataset $dataset, string $filePath, bool $dryRun): void
    {
        $this->newLine();
        $this->info("📊 Import Results Summary:");
        $this->info("==========================");
        $this->info("Dataset: {$dataset->name}");
        $this->info("File: " . basename($filePath));
        $this->info("Processed: {$this->stats['processed']} records");
        $this->info("Created: {$this->stats['created']} buildings");
        $this->info("Updated: {$this->stats['updated']} buildings");
        $this->info("Skipped: {$this->stats['skipped']} buildings");
        $this->info("Errors: {$this->stats['errors']} records");

        if ($dryRun) {
            $this->warn("🧪 This was a dry run - no data was actually imported");
        }

        // Show validation errors if any
        if (!empty($this->validationErrors)) {
            $this->newLine();
            $this->warn("⚠️ Validation Errors (" . count($this->validationErrors) . "):");

            // Show first 10 errors
            foreach (array_slice($this->validationErrors, 0, 10) as $error) {
                $this->error($error);
            }

            if (count($this->validationErrors) > 10) {
                $this->warn("... and " . (count($this->validationErrors) - 10) . " more errors");
            }
        }

        $this->newLine();

        if ($this->stats['errors'] === 0) {
            $this->info("✅ Import completed successfully!");
        } else {
            $this->warn("⚠️ Import completed with {$this->stats['errors']} errors");
        }
    }

    /**
     * Log the import activity
     */
    private function logImportActivity(Dataset $dataset, string $filePath, string $format): void
    {
        AuditLog::createEntry(
            userId: null, // System action
            action: 'building_data_import',
            targetType: 'dataset',
            targetId: $dataset->id,
            newValues: [
                'file_path' => basename($filePath),
                'format' => $format,
                'processed' => $this->stats['processed'],
                'created' => $this->stats['created'],
                'updated' => $this->stats['updated'],
                'skipped' => $this->stats['skipped'],
                'errors' => $this->stats['errors'],
            ],
            ipAddress: '127.0.0.1',
            userAgent: 'Laravel Artisan Command'
        );
    }
}
