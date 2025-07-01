<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Building;
use App\Models\Dataset;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Exception;

class GenerateAnomalyDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'anomaly:generate 
                            {dataset_id : The ID of the dataset to generate data for}
                            {--reference-csv= : Path to reference CSV file for data patterns}
                            {--anomaly-rate=0.15 : Percentage of buildings to mark as anomalies (0.0-1.0)}
                            {--dry-run : Show what would be generated without actually saving}
                            {--batch-size=100 : Number of records to process in each batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate realistic anomaly detection data for existing buildings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $datasetId = $this->argument('dataset_id');
        $referenceCsv = $this->option('reference-csv');
        $anomalyRate = (float) $this->option('anomaly-rate');
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');

        $this->info("🔄 Starting anomaly data generation process...");

        // Validate dataset exists
        $dataset = Dataset::find($datasetId);
        if (!$dataset) {
            $this->error("❌ Dataset with ID {$datasetId} not found!");
            return Command::FAILURE;
        }

        // Validate anomaly rate
        if ($anomalyRate < 0 || $anomalyRate > 1) {
            $this->error("❌ Anomaly rate must be between 0.0 and 1.0");
            return Command::FAILURE;
        }

        $this->info("📊 Dataset: {$dataset->name} (ID: {$dataset->id})");
        $this->info("📈 Anomaly Rate: " . ($anomalyRate * 100) . "%");
        $this->info("📦 Batch Size: {$batchSize}");

        if ($referenceCsv) {
            $this->info("📁 Reference CSV: {$referenceCsv}");
        }

        if ($dryRun) {
            $this->warn("🧪 DRY RUN MODE - No changes will be made");
        }

        $this->newLine();

        try {
            // Analyze reference data if provided
            $referenceStats = null;
            if ($referenceCsv && file_exists($referenceCsv)) {
                $referenceStats = $this->analyzeReferenceData($referenceCsv);
                $this->displayReferenceStats($referenceStats);
            }

            $stats = $this->generateAnomalyData($datasetId, $anomalyRate, $dryRun, $batchSize, $referenceStats);
            $this->displayGenerationStats($stats);

            if (!$dryRun && $stats['updated'] > 0) {
                // Log the generation action
                AuditLog::createEntry(
                    userId: null, // System action
                    action: 'anomaly_data_generated',
                    targetType: 'dataset',
                    targetId: $dataset->id,
                    oldValues: null,
                    newValues: [
                        'anomaly_rate' => $anomalyRate,
                        'reference_csv' => $referenceCsv,
                        'records_processed' => $stats['processed'],
                        'records_updated' => $stats['updated'],
                        'anomalies_generated' => $stats['anomalies']
                    ],
                    ipAddress: '127.0.0.1',
                    userAgent: 'Laravel Artisan Command'
                );

                $this->newLine();
                $this->info("✅ Anomaly data generation completed successfully!");
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("❌ Generation failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Analyze reference CSV data to understand patterns
     */
    private function analyzeReferenceData(string $csvFile): array
    {
        $this->info("🔍 Analyzing reference data patterns...");
        
        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            throw new Exception("Could not open reference CSV file: {$csvFile}");
        }

        $header = fgetcsv($handle);
        $stats = [
            'total_records' => 0,
            'numeric_columns' => [],
            'patterns' => []
        ];

        // Find numeric columns
        $numericColumns = [];
        $sampleRows = [];
        
        // Read first 100 rows to identify patterns
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false && $rowCount < 100) {
            $stats['total_records']++;
            $sampleRows[] = $row;
            $rowCount++;
        }

        // Analyze column types and ranges
        foreach ($header as $index => $columnName) {
            $values = array_column($sampleRows, $index);
            $numericValues = array_filter($values, 'is_numeric');
            
            if (count($numericValues) > count($values) * 0.8) { // 80% numeric
                $numericValues = array_map('floatval', $numericValues);
                $stats['numeric_columns'][$columnName] = [
                    'min' => min($numericValues),
                    'max' => max($numericValues),
                    'avg' => array_sum($numericValues) / count($numericValues),
                    'std' => $this->calculateStandardDeviation($numericValues)
                ];
            }
        }

        fclose($handle);
        return $stats;
    }

    /**
     * Generate anomaly data for buildings
     */
    private function generateAnomalyData(int $datasetId, float $anomalyRate, bool $dryRun, int $batchSize, ?array $referenceStats): array
    {
        $stats = [
            'processed' => 0,
            'updated' => 0,
            'anomalies' => 0
        ];

        // Get total building count
        $totalBuildings = Building::where('dataset_id', $datasetId)->count();
        $this->info("🏢 Total buildings in dataset: " . number_format($totalBuildings));

        if ($totalBuildings === 0) {
            $this->warn("⚠️ No buildings found in dataset {$datasetId}");
            return $stats;
        }

        $progressBar = $this->output->createProgressBar($totalBuildings);
        $progressBar->setFormat('Generating: %current%/%max% [%bar%] %percent:3s%% %memory:6s%');

        // Process buildings in batches
        Building::where('dataset_id', $datasetId)
            ->chunkById($batchSize, function ($buildings) use (&$stats, $anomalyRate, $dryRun, $referenceStats, $progressBar) {
                $updates = [];
                
                foreach ($buildings as $building) {
                    $stats['processed']++;
                    
                    // Generate anomaly data
                    $anomalyData = $this->generateBuildingAnomalyData($building, $anomalyRate, $referenceStats);
                    
                    if ($anomalyData['is_anomaly']) {
                        $stats['anomalies']++;
                    }

                    if (!$dryRun) {
                        $updates[] = [
                            'gid' => $building->gid,
                            'data' => $anomalyData
                        ];
                    }
                    
                    $progressBar->advance();
                }

                // Batch update
                if (!$dryRun && !empty($updates)) {
                    foreach ($updates as $update) {
                        Building::where('gid', $update['gid'])
                            ->update(array_merge($update['data'], ['last_analyzed_at' => now()]));
                        $stats['updated']++;
                    }
                }
            });

        $progressBar->finish();
        $this->newLine();

        return $stats;
    }

    /**
     * Generate anomaly data for a single building
     */
    private function generateBuildingAnomalyData($building, float $anomalyRate, ?array $referenceStats): array
    {
        // Determine if this building should be an anomaly
        $isAnomaly = mt_rand() / mt_getrandmax() < $anomalyRate;
        
        // Base heat loss values (using realistic ranges)
        $baseHeatLoss = $this->generateRealisticHeatLoss($building, $referenceStats);
        $referenceHeatLoss = $baseHeatLoss * (0.8 + (mt_rand() / mt_getrandmax()) * 0.4); // ±20% variation
        
        // Calculate differences
        $heatLossDifference = $baseHeatLoss - $referenceHeatLoss;
        $absHeatLossDifference = abs($heatLossDifference);
        
        // Set threshold (typically 10-30% of reference value)
        $threshold = $referenceHeatLoss * (0.1 + (mt_rand() / mt_getrandmax()) * 0.2);
        
        // Adjust for anomalies
        if ($isAnomaly) {
            // Make anomalies exceed threshold significantly
            $multiplier = 1.5 + (mt_rand() / mt_getrandmax()) * 2; // 1.5x to 3.5x threshold
            $baseHeatLoss = $referenceHeatLoss + ($threshold * $multiplier);
            $heatLossDifference = $baseHeatLoss - $referenceHeatLoss;
            $absHeatLossDifference = abs($heatLossDifference);
        }
        
        // Generate confidence score
        $confidence = $isAnomaly 
            ? 0.7 + (mt_rand() / mt_getrandmax()) * 0.3  // 70-100% for anomalies
            : 0.5 + (mt_rand() / mt_getrandmax()) * 0.4; // 50-90% for normal
        
        return [
            'average_heatloss' => round($baseHeatLoss, 2),
            'reference_heatloss' => round($referenceHeatLoss, 2),
            'heatloss_difference' => round($heatLossDifference, 2),
            'abs_heatloss_difference' => round($absHeatLossDifference, 2),
            'threshold' => round($threshold, 2),
            'is_anomaly' => $isAnomaly,
            'confidence' => round($confidence, 3)
        ];
    }

    /**
     * Generate realistic heat loss values based on building characteristics
     */
    private function generateRealisticHeatLoss($building, ?array $referenceStats): float
    {
        // Base heat loss ranges (kWh/m²/year or similar units)
        $baseRange = [50, 300]; // Typical range for buildings
        
        // Use reference stats if available
        if ($referenceStats && isset($referenceStats['numeric_columns']['average_heatloss'])) {
            $refStats = $referenceStats['numeric_columns']['average_heatloss'];
            $baseRange = [$refStats['min'], $refStats['max']];
        }
        
        // Generate base value with some randomness
        $baseValue = $baseRange[0] + (mt_rand() / mt_getrandmax()) * ($baseRange[1] - $baseRange[0]);
        
        // Add some variation based on building geometry if available
        if ($building->geometry) {
            // Larger buildings might have different heat loss patterns
            // This is a simplified approach
            $geometryFactor = 0.9 + (mt_rand() / mt_getrandmax()) * 0.2; // ±10% variation
            $baseValue *= $geometryFactor;
        }
        
        return max(10, $baseValue); // Minimum reasonable value
    }

    /**
     * Calculate standard deviation
     */
    private function calculateStandardDeviation(array $values): float
    {
        $count = count($values);
        if ($count === 0) return 0;
        
        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / $count;
        
        return sqrt($variance);
    }

    /**
     * Display reference statistics
     */
    private function displayReferenceStats(?array $stats): void
    {
        if (!$stats) return;
        
        $this->info("📊 Reference Data Analysis:");
        $this->info("===========================");
        $this->info("📋 Total Records: " . number_format($stats['total_records']));
        
        if (!empty($stats['numeric_columns'])) {
            $this->info("🔢 Numeric Columns Found:");
            foreach ($stats['numeric_columns'] as $column => $columnStats) {
                $this->info("   • {$column}:");
                $this->info("     - Range: " . round($columnStats['min'], 2) . " to " . round($columnStats['max'], 2));
                $this->info("     - Average: " . round($columnStats['avg'], 2));
                $this->info("     - Std Dev: " . round($columnStats['std'], 2));
            }
        }
        $this->newLine();
    }

    /**
     * Display generation statistics
     */
    private function displayGenerationStats(array $stats): void
    {
        $this->newLine();
        $this->info("📊 Generation Statistics:");
        $this->info("========================");
        $this->info("📋 Buildings Processed: " . number_format($stats['processed']));
        $this->info("✅ Buildings Updated: " . number_format($stats['updated']));
        $this->info("🚨 Anomalies Generated: " . number_format($stats['anomalies']));
        
        if ($stats['processed'] > 0) {
            $anomalyRate = round(($stats['anomalies'] / $stats['processed']) * 100, 2);
            $this->info("📈 Actual Anomaly Rate: {$anomalyRate}%");
        }
    }
}