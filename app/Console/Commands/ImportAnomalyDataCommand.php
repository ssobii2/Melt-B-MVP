<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Building;
use App\Models\Dataset;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

class ImportAnomalyDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'anomaly:import 
                            {csv_file : Path to the CSV file containing anomaly data}
                            {dataset_id : The ID of the dataset to update}
                            {--dry-run : Show what would be imported without actually importing}
                            {--batch-size=100 : Number of records to process in each batch}
                            {--skip-missing : Skip buildings that don\'t exist in the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import anomaly detection data from CSV file into buildings table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $csvFile = $this->argument('csv_file');
        $datasetId = $this->argument('dataset_id');
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');
        $skipMissing = $this->option('skip-missing');

        $this->info("🔄 Starting anomaly data import process...");

        // Validate dataset exists
        $dataset = Dataset::find($datasetId);
        if (!$dataset) {
            $this->error("❌ Dataset with ID {$datasetId} not found!");
            return Command::FAILURE;
        }

        // Validate CSV file exists
        if (!file_exists($csvFile)) {
            $this->error("❌ CSV file not found: {$csvFile}");
            return Command::FAILURE;
        }

        $this->info("📊 Dataset: {$dataset->name} (ID: {$dataset->id})");
        $this->info("📁 CSV File: {$csvFile}");
        $this->info("📦 Batch Size: {$batchSize}");

        if ($dryRun) {
            $this->warn("🧪 DRY RUN MODE - No changes will be made");
        }

        if ($skipMissing) {
            $this->info("⏭️ Skip Missing: Buildings not found in database will be skipped");
        }

        $this->newLine();

        try {
            $stats = $this->processCSVFile($csvFile, $datasetId, $dryRun, $batchSize, $skipMissing);
            $this->displayImportStats($stats);

            if (!$dryRun && $stats['updated'] > 0) {
                // Log the import action
                AuditLog::createEntry(
                    userId: null, // System action
                    action: 'anomaly_data_imported',
                    targetType: 'dataset',
                    targetId: $dataset->id,
                    oldValues: null,
                    newValues: [
                        'csv_file' => $csvFile,
                        'records_processed' => $stats['processed'],
                        'records_updated' => $stats['updated'],
                        'records_skipped' => $stats['skipped'],
                        'records_failed' => $stats['failed']
                    ],
                    ipAddress: '127.0.0.1',
                    userAgent: 'Laravel Artisan Command'
                );

                $this->newLine();
                $this->info("✅ Anomaly data import completed successfully!");
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("❌ Import failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Process the CSV file and import anomaly data
     */
    private function processCSVFile(string $csvFile, int $datasetId, bool $dryRun, int $batchSize, bool $skipMissing): array
    {
        $stats = [
            'processed' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0
        ];

        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            throw new Exception("Could not open CSV file: {$csvFile}");
        }

        // Read and validate header
        $header = fgetcsv($handle);
        if (!$header) {
            throw new Exception("Could not read CSV header");
        }

        $this->info("📋 CSV Header: " . implode(', ', $header));
        $this->newLine();

        // Map CSV columns to database fields
        $columnMap = $this->mapCSVColumns($header);
        $this->displayColumnMapping($columnMap);

        $batch = [];
        $lineNumber = 1; // Start from 1 (header is line 0)

        $progressBar = $this->output->createProgressBar();
        $progressBar->setFormat('Processing: %current% records [%bar%] %percent:3s%% %memory:6s%');

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            $stats['processed']++;

            try {
                $buildingData = $this->mapRowToBuilding($row, $columnMap, $header);
                
                if ($buildingData) {
                    $batch[] = [
                        'building_id' => $buildingData['building_id'],
                        'data' => $buildingData,
                        'line' => $lineNumber
                    ];

                    // Process batch when it reaches the specified size
                    if (count($batch) >= $batchSize) {
                        $batchStats = $this->processBatch($batch, $datasetId, $dryRun, $skipMissing);
                        $stats['updated'] += $batchStats['updated'];
                        $stats['skipped'] += $batchStats['skipped'];
                        $stats['failed'] += $batchStats['failed'];
                        $batch = [];
                    }
                }
            } catch (Exception $e) {
                $stats['failed']++;
                $this->warn("⚠️ Error processing line {$lineNumber}: " . $e->getMessage());
            }

            $progressBar->advance();
        }

        // Process remaining batch
        if (!empty($batch)) {
            $batchStats = $this->processBatch($batch, $datasetId, $dryRun, $skipMissing);
            $stats['updated'] += $batchStats['updated'];
            $stats['skipped'] += $batchStats['skipped'];
            $stats['failed'] += $batchStats['failed'];
        }

        $progressBar->finish();
        $this->newLine();
        fclose($handle);

        return $stats;
    }

    /**
     * Map CSV columns to database fields
     */
    private function mapCSVColumns(array $header): array
    {
        $map = [];
        
        foreach ($header as $index => $column) {
            $column = trim(strtolower($column));
            
            switch ($column) {
                case 'building_id':
                    $map['building_id'] = $index;
                    break;
                case 'average_heatloss':
                    $map['average_heatloss'] = $index;
                    break;
                case 'reference heatloss':
                    $map['reference_heatloss'] = $index;
                    break;
                case 'heatloss_difference':
                    $map['heatloss_difference'] = $index;
                    break;
                case 'abs_heatloss_difference':
                    $map['abs_heatloss_difference'] = $index;
                    break;
                case 'threshold':
                    $map['threshold'] = $index;
                    break;
                case 'is_anomaly':
                    $map['is_anomaly'] = $index;
                    break;
                case 'confidence':
                    $map['confidence'] = $index;
                    break;
                case 'predicted_class':
                    $map['building_type_classification'] = $index;
                    break;
            }
        }

        return $map;
    }

    /**
     * Map a CSV row to building data
     */
    private function mapRowToBuilding(array $row, array $columnMap, array $header): ?array
    {
        if (!isset($columnMap['building_id'])) {
            throw new Exception("building_id column not found in CSV");
        }

        $buildingId = $row[$columnMap['building_id']] ?? null;
        if (empty($buildingId)) {
            return null;
        }

        $data = ['building_id' => $buildingId];

        // Map each available field
        foreach ($columnMap as $field => $index) {
            if ($field === 'building_id') continue;
            
            $value = $row[$index] ?? null;
            
            if ($value !== null && $value !== '') {
                // Handle boolean conversion for is_anomaly
                if ($field === 'is_anomaly') {
                    $data[$field] = in_array(strtolower(trim($value)), ['true', '1', 'yes', 'y']);
                } 
                // Handle numeric fields
                elseif (in_array($field, ['average_heatloss', 'reference_heatloss', 'heatloss_difference', 'abs_heatloss_difference', 'threshold', 'confidence'])) {
                    $data[$field] = is_numeric($value) ? (float) $value : null;
                }
                // Handle string fields
                else {
                    $data[$field] = trim($value);
                }
            }
        }

        return $data;
    }

    /**
     * Process a batch of building updates
     */
    private function processBatch(array $batch, int $datasetId, bool $dryRun, bool $skipMissing): array
    {
        $stats = ['updated' => 0, 'skipped' => 0, 'failed' => 0];
        
        // Get all building IDs in this batch
        $buildingIds = array_column($batch, 'building_id');
        
        // Find existing buildings
        $existingBuildings = Building::where('dataset_id', $datasetId)
            ->whereIn('gid', $buildingIds)
            ->pluck('gid')
            ->toArray();

        foreach ($batch as $item) {
            $buildingId = $item['building_id'];
            $data = $item['data'];
            $lineNumber = $item['line'];

            try {
                if (!in_array($buildingId, $existingBuildings)) {
                    if ($skipMissing) {
                        $stats['skipped']++;
                        continue;
                    } else {
                        throw new Exception("Building {$buildingId} not found in dataset {$datasetId}");
                    }
                }

                if (!$dryRun) {
                    // Remove building_id from data as it's not a fillable field
                    unset($data['building_id']);
                    
                    // Update the building
                    Building::where('gid', $buildingId)
                        ->where('dataset_id', $datasetId)
                        ->update(array_merge($data, ['last_analyzed_at' => now()]));
                }

                $stats['updated']++;
            } catch (Exception $e) {
                $stats['failed']++;
                $this->warn("⚠️ Failed to update building {$buildingId} (line {$lineNumber}): " . $e->getMessage());
            }
        }

        return $stats;
    }

    /**
     * Display column mapping
     */
    private function displayColumnMapping(array $columnMap): void
    {
        $this->info("🗂️ Column Mapping:");
        foreach ($columnMap as $field => $index) {
            $this->info("   • {$field} ← Column {$index}");
        }
        $this->newLine();
    }

    /**
     * Display import statistics
     */
    private function displayImportStats(array $stats): void
    {
        $this->newLine();
        $this->info("📊 Import Statistics:");
        $this->info("===================");
        $this->info("📋 Records Processed: " . number_format($stats['processed']));
        $this->info("✅ Records Updated: " . number_format($stats['updated']));
        $this->info("⏭️ Records Skipped: " . number_format($stats['skipped']));
        $this->info("❌ Records Failed: " . number_format($stats['failed']));
        
        if ($stats['processed'] > 0) {
            $successRate = round(($stats['updated'] / $stats['processed']) * 100, 2);
            $this->info("📈 Success Rate: {$successRate}%");
        }
    }
}