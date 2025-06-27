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
            [
                'name' => 'Paris Building Footprints BDTOPO 2025-Q1',
                'data_type' => 'building_footprints',
                'version' => '2025.1.0',
                'description' => 'Official French IGN building footprints for Paris (Department 075) from BDTOPO database',
                'storage_location' => storage_path('data/BDTOPO/1_DONNEES_LIVRAISON_2025-03-00288/BDT_3-4_SHP_LAMB93_D075_ED2025-03-15/BATI'),
                'metadata' => [
                    'source' => 'IGN France - Institut Géographique National',
                    'format' => 'Shapefile (converted to PostGIS)',
                    'projection' => 'RGF93 Lambert 93 (EPSG:2154) converted to WGS84 (EPSG:4326)',
                    'spatial_resolution' => 'Building-level precision',
                    'temporal_coverage' => '2025-03-15',
                    'geographic_extent' => 'Paris Department 075',
                    'size_mb' => 188,
                ],
            ],
            [
                'name' => 'Paris Thermal Imagery BOA 2023-Q4',
                'data_type' => 'thermal_rasters',
                'version' => '2023.4.0',
                'description' => 'Satellite thermal brightness temperature imagery for Paris from BOA (Bottom of Atmosphere) products',
                'storage_location' => storage_path('data/Paris - BOA Products'),
                'metadata' => [
                    'source' => 'AI Team / Data Science Team - BOA Products',
                    'format' => 'GeoTIFF thermal imagery',
                    'dates' => ['2023-10-09', '2023-10-23'],
                    'products' => ['thermal_radiance', 'brightness_temperature'],
                    'spatial_resolution' => '30m pixel resolution',
                    'temporal_coverage' => 'October 2023',
                    'geographic_extent' => 'Paris metropolitan area',
                    'size_mb' => 12,
                ],
            ],
        ];

        foreach ($datasets as $dataset) {
            Dataset::create($dataset);
        }

        $this->command->info('✅ Created ' . count($datasets) . ' real Paris datasets');
    }
}
