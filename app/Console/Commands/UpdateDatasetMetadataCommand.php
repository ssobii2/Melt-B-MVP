<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Dataset;
use App\Models\Building;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Exception;

class UpdateDatasetMetadataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dataset:update-metadata 
                            {dataset_id : The ID of the dataset to update}
                            {--storage-location= : Update storage location}
                            {--dataset-version= : Update dataset version}
                            {--calculate-stats : Calculate and update statistics from actual data}
                            {--dry-run : Show what would be updated without actually updating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update dataset metadata including storage location, version, and calculated statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $datasetId = $this->argument('dataset_id');
        $storageLocation = $this->option('storage-location');
        $version = $this->option('dataset-version');
        $calculateStats = $this->option('calculate-stats');
        $dryRun = $this->option('dry-run');

        $this->info("🔄 Starting dataset metadata update process...");

        // Validate dataset exists
        $dataset = Dataset::find($datasetId);
        if (!$dataset) {
            $this->error("❌ Dataset with ID {$datasetId} not found!");
            return Command::FAILURE;
        }

        $this->info("📊 Dataset: {$dataset->name} (ID: {$dataset->id})");
        $this->info("📅 Current version: {$dataset->version}");
        $this->info("📁 Current storage location: {$dataset->storage_location}");

        if ($dryRun) {
            $this->warn("🧪 DRY RUN MODE - No changes will be made");
        }

        $this->newLine();

        try {
            $updateData = [];
            $oldValues = [];

            // Prepare update data
            if ($storageLocation) {
                $oldValues['storage_location'] = $dataset->storage_location;
                $updateData['storage_location'] = $storageLocation;
                $this->info("📁 New storage location: {$storageLocation}");
            }

            if ($version) {
                $oldValues['version'] = $dataset->version;
                $updateData['version'] = $version;
                $this->info("📅 New version: {$version}");
            }

            // Calculate statistics if requested
            if ($calculateStats) {
                $this->info("🧮 Calculating dataset statistics...");
                $stats = $this->calculateDatasetStatistics($dataset);

                if ($stats) {
                    $oldValues['metadata'] = $dataset->metadata;
                    $newMetadata = array_merge($dataset->metadata ?? [], $stats);
                    $updateData['metadata'] = $newMetadata;

                    $this->displayStatistics($stats);
                } else {
                    $this->warn("⚠️ No building data found for this dataset");
                }
            }

            // Apply updates
            if (!empty($updateData)) {
                if (!$dryRun) {
                    $dataset->update($updateData);

                    // Log the metadata update
                    AuditLog::createEntry(
                        userId: null, // System action
                        action: 'dataset_metadata_updated',
                        targetType: 'dataset',
                        targetId: $dataset->id,
                        oldValues: $oldValues,
                        newValues: $updateData,
                        ipAddress: '127.0.0.1',
                        userAgent: 'Laravel Artisan Command'
                    );

                    $this->newLine();
                    $this->info("✅ Dataset metadata updated successfully!");
                } else {
                    $this->newLine();
                    $this->info("🧪 Would update the following metadata:");
                    foreach ($updateData as $key => $value) {
                        if ($key === 'metadata') {
                            $this->info("  • {$key}: " . json_encode($value, JSON_PRETTY_PRINT));
                        } else {
                            $this->info("  • {$key}: {$value}");
                        }
                    }
                }
            } else {
                $this->warn("⚠️ No updates specified. Use --storage-location, --version, or --calculate-stats options.");
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("❌ Metadata update failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Calculate statistics from the actual building data
     */
    private function calculateDatasetStatistics(Dataset $dataset): ?array
    {
        try {
            $buildingCount = Building::where('dataset_id', $dataset->id)->count();

            if ($buildingCount === 0) {
                return null;
            }

            // Calculate building statistics
            $stats = DB::table('buildings')
                ->where('dataset_id', $dataset->id)
                ->selectRaw('
                    COUNT(*) as total_buildings,
                    SUM(CASE WHEN is_anomaly = true THEN 1 ELSE 0 END) as total_anomalies,
                    AVG(average_heatloss) as avg_heatloss,
                    AVG(heatloss_difference) as avg_heatloss_difference,
                    MIN(heatloss_difference) as min_heatloss_difference,
                    MAX(heatloss_difference) as max_heatloss_difference,
                    SUM(co2_savings_estimate) as total_co2_savings,
                    AVG(co2_savings_estimate) as avg_co2_savings
                ')
                ->first();

            // Building type distribution
            $typeDistribution = DB::table('buildings')
                ->where('dataset_id', $dataset->id)
                ->select('building_type_classification')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('building_type_classification')
                ->pluck('count', 'building_type_classification')
                ->toArray();



            // Spatial coverage (bounding box)
            $spatialCoverage = DB::select('
                SELECT 
                    ST_XMin(ST_Extent(geometry)) as min_lng,
                    ST_YMin(ST_Extent(geometry)) as min_lat,
                    ST_XMax(ST_Extent(geometry)) as max_lng,
                    ST_YMax(ST_Extent(geometry)) as max_lat
                FROM buildings 
                WHERE dataset_id = ?
            ', [$dataset->id]);

            $boundingBox = null;
            if (!empty($spatialCoverage) && $spatialCoverage[0]->min_lng !== null) {
                $bbox = $spatialCoverage[0];
                $boundingBox = [
                    'min_lng' => (float) $bbox->min_lng,
                    'min_lat' => (float) $bbox->min_lat,
                    'max_lng' => (float) $bbox->max_lng,
                    'max_lat' => (float) $bbox->max_lat,
                ];
            }

            return [
                'calculated_at' => now()->toISOString(),
                'total_buildings' => (int) $stats->total_buildings,
                
                // NEW: Anomaly Statistics
                'anomaly_statistics' => [
                    'total_anomalies' => (int) $stats->total_anomalies,
                    'anomaly_percentage' => round(((int) $stats->total_anomalies / (int) $stats->total_buildings) * 100, 2),
                ],

                // NEW: Heat Loss Statistics
                'heatloss_statistics' => [
                    'average' => round((float) $stats->avg_heatloss, 2),
                    'average_difference' => round((float) $stats->avg_heatloss_difference, 2),
                    'min_difference' => round((float) $stats->min_heatloss_difference, 2),
                    'max_difference' => round((float) $stats->max_heatloss_difference, 2),
                ],

                'co2_statistics' => [
                    'total_savings_estimate' => round((float) $stats->total_co2_savings, 2),
                    'average_savings_estimate' => round((float) $stats->avg_co2_savings, 2),
                ],
                'building_type_distribution' => $typeDistribution,
                'spatial_coverage' => $boundingBox,
                'data_coverage' => [
                    'has_co2_data' => Building::where('dataset_id', $dataset->id)
                        ->whereNotNull('co2_savings_estimate')
                        ->count(),
                    'has_address_data' => Building::where('dataset_id', $dataset->id)
                        ->whereNotNull('address')
                        ->count(),
                    'has_anomaly_data' => Building::where('dataset_id', $dataset->id)
                        ->whereNotNull('is_anomaly')
                        ->count(),
                ],
            ];
        } catch (Exception $e) {
            $this->error("❌ Error calculating statistics: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Display calculated statistics in a formatted way
     */
    private function displayStatistics(array $stats): void
    {
        $this->newLine();
        $this->info("📊 Calculated Dataset Statistics:");
        $this->info("================================");

        $this->info("🏢 Total Buildings: " . number_format($stats['total_buildings']));

        if (isset($stats['anomaly_statistics'])) {
            $this->info("🚨 Anomaly Statistics:");
            $this->info("   • Total Anomalies: " . number_format($stats['anomaly_statistics']['total_anomalies']));
            $this->info("   • Anomaly Percentage: " . $stats['anomaly_statistics']['anomaly_percentage'] . "%");
        }

        if (isset($stats['heatloss_statistics'])) {
            $this->info("🌡️ Heat Loss Statistics:");
            $this->info("   • Average Heat Loss: " . $stats['heatloss_statistics']['average']);
            $this->info("   • Average Difference: " . $stats['heatloss_statistics']['average_difference']);
            $this->info("   • Difference Range: " . $stats['heatloss_statistics']['min_difference'] . " - " . $stats['heatloss_statistics']['max_difference']);
        }

        if (isset($stats['co2_statistics'])) {
            $this->info("💨 CO2 Savings Estimates:");
            $this->info("   • Total: " . number_format($stats['co2_statistics']['total_savings_estimate'], 2) . " tonnes");
            $this->info("   • Average per building: " . number_format($stats['co2_statistics']['average_savings_estimate'], 2) . " tonnes");
        }

        if (isset($stats['building_type_distribution'])) {
            $this->info("🏗️ Building Types:");
            foreach ($stats['building_type_distribution'] as $type => $count) {
                $this->info("   • " . ucfirst($type) . ": " . number_format($count));
            }
        }

        if (isset($stats['spatial_coverage'])) {
            $this->info("🗺️ Spatial Coverage:");
            $bbox = $stats['spatial_coverage'];
            $this->info("   • SW Corner: {$bbox['min_lat']}, {$bbox['min_lng']}");
            $this->info("   • NE Corner: {$bbox['max_lat']}, {$bbox['max_lng']}");
        }

        if (isset($stats['data_coverage'])) {
            $coverage = $stats['data_coverage'];
            $total = $stats['total_buildings'];
            $this->info("📋 Data Completeness:");
            $this->info("   • CO2 Data: " . number_format($coverage['has_co2_data']) . " / " . number_format($total) . " (" . round(($coverage['has_co2_data'] / $total) * 100, 1) . "%)");
            $this->info("   • Address Data: " . number_format($coverage['has_address_data']) . " / " . number_format($total) . " (" . round(($coverage['has_address_data'] / $total) * 100, 1) . "%)");
            $this->info("   • Anomaly Data: " . number_format($coverage['has_anomaly_data']) . " / " . number_format($total) . " (" . round(($coverage['has_anomaly_data'] / $total) * 100, 1) . "%)");
        }

        $this->info("🕐 Calculated at: " . $stats['calculated_at']);
    }
}
