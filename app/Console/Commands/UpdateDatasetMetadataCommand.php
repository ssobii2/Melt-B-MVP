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
                    AVG(thermal_loss_index_tli) as avg_tli,
                    MIN(thermal_loss_index_tli) as min_tli,
                    MAX(thermal_loss_index_tli) as max_tli,
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

            // TLI distribution (low, medium, high)
            $tliDistribution = DB::table('buildings')
                ->where('dataset_id', $dataset->id)
                ->selectRaw('
                    SUM(CASE WHEN thermal_loss_index_tli <= 30 THEN 1 ELSE 0 END) as low_tli,
                    SUM(CASE WHEN thermal_loss_index_tli > 30 AND thermal_loss_index_tli <= 70 THEN 1 ELSE 0 END) as medium_tli,
                    SUM(CASE WHEN thermal_loss_index_tli > 70 THEN 1 ELSE 0 END) as high_tli
                ')
                ->first();

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
                'tli_statistics' => [
                    'average' => round((float) $stats->avg_tli, 2),
                    'minimum' => (int) $stats->min_tli,
                    'maximum' => (int) $stats->max_tli,
                ],
                'tli_distribution' => [
                    'low' => (int) $tliDistribution->low_tli,
                    'medium' => (int) $tliDistribution->medium_tli,
                    'high' => (int) $tliDistribution->high_tli,
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
                    'has_renovation_data' => Building::where('dataset_id', $dataset->id)
                        ->whereNotNull('before_renovation_tli')
                        ->whereNotNull('after_renovation_tli')
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

        if (isset($stats['tli_statistics'])) {
            $this->info("🌡️ TLI Statistics:");
            $this->info("   • Average: " . $stats['tli_statistics']['average']);
            $this->info("   • Range: " . $stats['tli_statistics']['minimum'] . " - " . $stats['tli_statistics']['maximum']);
        }

        if (isset($stats['tli_distribution'])) {
            $this->info("📈 TLI Distribution:");
            $this->info("   • Low (≤30): " . number_format($stats['tli_distribution']['low']));
            $this->info("   • Medium (31-70): " . number_format($stats['tli_distribution']['medium']));
            $this->info("   • High (>70): " . number_format($stats['tli_distribution']['high']));
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
            $this->info("   • Renovation Data: " . number_format($coverage['has_renovation_data']) . " / " . number_format($total) . " (" . round(($coverage['has_renovation_data'] / $total) * 100, 1) . "%)");
        }

        $this->info("🕐 Calculated at: " . $stats['calculated_at']);
    }
}
