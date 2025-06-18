<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Dataset;

class DatasetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datasets = [
            [
                'name' => 'Thermal Raster v2024-Q4 Debrecen',
                'description' => 'High-resolution thermal imagery of Debrecen city center captured in Q4 2024',
                'data_type' => 'thermal-raster',
                'storage_location' => 's3://melt-b-data/thermal-rasters/debrecen-2024-q4/',
                'version' => '2024.4.1',
            ],
            [
                'name' => 'Building Data v2024-Q4 Debrecen',
                'description' => 'Processed building footprints with thermal loss analysis for Debrecen',
                'data_type' => 'building-data',
                'storage_location' => 's3://melt-b-data/building-data/debrecen-2024-q4/',
                'version' => '2024.4.1',
            ],
            [
                'name' => 'Thermal Raster v2024-Q3 Budapest District V',
                'description' => 'Thermal analysis of Budapest District V historical center',
                'data_type' => 'thermal-raster',
                'storage_location' => 's3://melt-b-data/thermal-rasters/budapest-v-2024-q3/',
                'version' => '2024.3.1',
            ],
            [
                'name' => 'Building Data v2024-Q3 Budapest District V',
                'description' => 'Building thermal performance data for Budapest District V',
                'data_type' => 'building-data',
                'storage_location' => 's3://melt-b-data/building-data/budapest-v-2024-q3/',
                'version' => '2024.3.1',
            ],
        ];

        foreach ($datasets as $dataset) {
            Dataset::create($dataset);
        }
    }
}
