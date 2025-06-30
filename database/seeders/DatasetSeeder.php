<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Dataset;

class DatasetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     *  DATASET TAXONOMY UPDATED FOR ANOMALY DETECTION
     *  -------------------------------------------------------------
     *  • Each dataset now focuses on building anomalies data
     *  • Data type standardized to 'building_anomalies'
     *  • Thermal raster datasets removed per REFACTOR.md
     *  • Storage locations updated for new data structure
     */
    public function run(): void
    {
        Dataset::truncate();

        $datasets = [
            [
                'name' => 'Paris Building Anomalies Analysis 2025-Q1',
                'data_type' => 'building_anomalies',
                'version' => '2025.1.0',
                'description' => 'Building thermal anomaly detection analysis for Paris based on BDTOPO building footprints and thermal imagery',
                'storage_location' => storage_path('data/paris_anomalies_analysis_2025q1.csv'),
                'metadata' => [
                    'source' => 'MELT-B Analysis Team - Anomaly Detection Pipeline',
                    'format' => 'CSV with anomaly detection results',
                    'base_data' => 'IGN BDTOPO + BOA Thermal Products',
                    'analysis_date' => '2025-01-15',
                    'spatial_resolution' => 'Building-level precision',
                    'temporal_coverage' => 'Analysis based on October 2023 thermal imagery',
                    'geographic_extent' => 'Paris Department 075',
                    'size_mb' => 45,
                    'anomaly_threshold' => 86.58,
                    'confidence_model' => 'ML-based confidence scoring',
                ],
            ],
        ];

        foreach ($datasets as $dataset) {
            Dataset::create($dataset);
        }

        $this->command->info('✅ Created ' . count($datasets) . ' anomaly detection datasets');
    }
}
