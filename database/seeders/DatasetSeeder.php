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
     *  DATASET TAXONOMY USED BY ABAC TEST-SUITE
     *  -------------------------------------------------------------
     *  • Each city has TWO logical datasets so every entitlement type
     *    can point at either raster or building data independently.
     *      – thermal_raster  (for map tiles)
     *      – building_data   (for polygons / tabular downloads)
     *  • We seed three cities so that our test-suite can assert that
     *    entitlement filters do not bleed across datasets.
     */
    public function run(): void
    {
        Dataset::truncate();

        $datasets = [
            // ───────────────── Debrecen ─────────────────
            [
                'name' => 'Thermal Raster 2024-Q4 Debrecen',
                'description' => 'Synthetic raster tiles coloured by TLI for Debrecen city',
                'data_type' => 'thermal_raster',
                'storage_location' => 'local://thermal_rasters/debrecen',
                'version' => '2024.4.0',
                'metadata' => [
                    'coverage_area' => 'Debrecen City Center',
                    'resolution' => '0.5m',
                    'capture_date' => '2024-01-15',
                    'bbox' => [21.6270, 47.5310, 21.6330, 47.5360],
                ],
            ],
            [
                'name' => 'Building Data 2024-Q4 Debrecen',
                'description' => 'Synthetic building footprints + TLI for Debrecen',
                'data_type' => 'building_data',
                'storage_location' => 'local://building_data/debrecen',
                'version' => '2024.4.0',
                'metadata' => [
                    'total_buildings' => 1500,
                    'last_updated' => '2024-01-15',
                    'data_source' => 'Synthetic Generation',
                    'bbox' => [21.6270, 47.5310, 21.6330, 47.5360],
                ],
            ],

            // ───────────────── Budapest ─────────────────
            [
                'name' => 'Thermal Raster 2024-Q3 Budapest District V',
                'description' => 'Synthetic raster tiles for the historical city centre',
                'data_type' => 'thermal_raster',
                'storage_location' => 'local://thermal_rasters/budapest_v',
                'version' => '2024.3.0',
                'metadata' => [
                    'coverage_area' => 'Budapest District V',
                    'resolution' => '0.5m',
                    'capture_date' => '2024-09-15',
                    'bbox' => [19.0390, 47.4970, 19.0440, 47.5000],
                ],
            ],
            [
                'name' => 'Building Data 2024-Q3 Budapest District V',
                'description' => 'Synthetic building dataset for Budapest district V',
                'data_type' => 'building_data',
                'storage_location' => 'local://building_data/budapest_v',
                'version' => '2024.3.0',
                'metadata' => [
                    'total_buildings' => 800,
                    'last_updated' => '2024-09-15',
                    'data_source' => 'Synthetic Generation',
                    'bbox' => [19.0390, 47.4970, 19.0440, 47.5000],
                ],
            ],

            // ───────────────── Copenhagen ─────────────────
            [
                'name' => 'Thermal Raster 2023-Q4 Copenhagen',
                'description' => 'Synthetic raster tiles for Frederiksberg + Nørrebro',
                'data_type' => 'thermal_raster',
                'storage_location' => 'local://thermal_rasters/copenhagen',
                'version' => '2023.4.0',
                'metadata' => [
                    'coverage_area' => 'Frederiksberg and Nørrebro',
                    'resolution' => '0.5m',
                    'capture_date' => '2023-12-15',
                    'bbox' => [12.5660, 55.6755, 12.5700, 55.6780],
                ],
            ],
            [
                'name' => 'Building Data 2023-Q4 Copenhagen',
                'description' => 'Synthetic building dataset for central Copenhagen',
                'data_type' => 'building_data',
                'storage_location' => 'local://building_data/copenhagen',
                'version' => '2023.4.0',
                'metadata' => [
                    'total_buildings' => 1200,
                    'last_updated' => '2023-12-15',
                    'data_source' => 'Synthetic Generation',
                    'bbox' => [12.5660, 55.6755, 12.5700, 55.6780],
                ],
            ],
        ];

        foreach ($datasets as $data) {
            Dataset::create($data);
        }

        $this->command->info('✅ Seeded '.count($datasets).' datasets for three cities');
    }
}
