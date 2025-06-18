<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Entitlement;
use App\Models\Dataset;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\LineString;

class EntitlementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $datasets = Dataset::all();

        if ($datasets->isEmpty()) {
            $this->command->warn('No datasets found. Please run DatasetSeeder first.');
            return;
        }

        $entitlements = [
            // DS-ALL: Full access to Debrecen thermal raster dataset
            [
                'type' => 'DS-ALL',
                'dataset_id' => $datasets->where('name', 'like', '%Debrecen%')->where('data_type', 'thermal-raster')->first()?->id ?? 1,
                'aoi_geom' => null,
                'building_gids' => null,
                'download_formats' => ['csv', 'json', 'geojson'],
                'expires_at' => now()->addMonths(6),
            ],

            // DS-AOI: Area of Interest for Debrecen city center
            [
                'type' => 'DS-AOI',
                'dataset_id' => $datasets->where('name', 'like', '%Debrecen%')->where('data_type', 'building-data')->first()?->id ?? 2,
                'aoi_geom' => new Polygon([
                    new LineString([
                        new Point(47.5316, 21.6273), // Debrecen city center polygon
                        new Point(47.5316, 21.6373),
                        new Point(47.5416, 21.6373),
                        new Point(47.5416, 21.6273),
                        new Point(47.5316, 21.6273), // Close the polygon
                    ])
                ]),
                'building_gids' => null,
                'download_formats' => ['csv', 'geojson'],
                'expires_at' => now()->addMonths(3),
            ],

            // DS-BLD: Specific buildings access
            [
                'type' => 'DS-BLD',
                'dataset_id' => $datasets->where('name', 'like', '%Budapest%')->where('data_type', 'building-data')->first()?->id ?? 4,
                'aoi_geom' => null,
                'building_gids' => ['BLDG_001', 'BLDG_002', 'BLDG_003', 'BLDG_004', 'BLDG_005'],
                'download_formats' => ['csv', 'json'],
                'expires_at' => now()->addMonth(),
            ],

            // TILES: Map tiles access for Budapest
            [
                'type' => 'TILES',
                'dataset_id' => $datasets->where('name', 'like', '%Budapest%')->where('data_type', 'thermal-raster')->first()?->id ?? 3,
                'aoi_geom' => new Polygon([
                    new LineString([
                        new Point(47.4979, 19.0402), // Budapest District V area
                        new Point(47.4979, 19.0502),
                        new Point(47.5079, 19.0502),
                        new Point(47.5079, 19.0402),
                        new Point(47.4979, 19.0402), // Close the polygon
                    ])
                ]),
                'building_gids' => null,
                'download_formats' => ['json'],
                'expires_at' => now()->addMonths(12),
            ],

            // DS-AOI: Larger area for testing
            [
                'type' => 'DS-AOI',
                'dataset_id' => $datasets->where('name', 'like', '%Debrecen%')->where('data_type', 'building-data')->first()?->id ?? 2,
                'aoi_geom' => new Polygon([
                    new LineString([
                        new Point(47.5200, 21.6200), // Larger Debrecen area
                        new Point(47.5200, 21.6500),
                        new Point(47.5500, 21.6500),
                        new Point(47.5500, 21.6200),
                        new Point(47.5200, 21.6200), // Close the polygon
                    ])
                ]),
                'building_gids' => null,
                'download_formats' => ['csv', 'geojson', 'excel'],
                'expires_at' => now()->addYears(2),
            ],

            // Expired entitlement for testing
            [
                'type' => 'DS-BLD',
                'dataset_id' => $datasets->first()->id,
                'aoi_geom' => null,
                'building_gids' => ['EXPIRED_001', 'EXPIRED_002'],
                'download_formats' => ['csv'],
                'expires_at' => now()->subDays(30), // Expired 30 days ago
            ],

            // Another DS-ALL for testing overlapping entitlements
            [
                'type' => 'DS-ALL',
                'dataset_id' => $datasets->where('name', 'like', '%Budapest%')->where('data_type', 'building-data')->first()?->id ?? 4,
                'aoi_geom' => null,
                'building_gids' => null,
                'download_formats' => ['csv', 'json', 'geojson', 'excel', 'pdf'],
                'expires_at' => null, // Never expires
            ],
        ];

        foreach ($entitlements as $entitlementData) {
            Entitlement::create($entitlementData);
        }

        $this->command->info('✅ Created ' . count($entitlements) . ' entitlements with various types and spatial data');
    }
}
