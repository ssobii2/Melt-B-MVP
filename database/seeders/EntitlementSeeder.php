<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Dataset;
use App\Models\Entitlement;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\Point;

class EntitlementSeeder extends Seeder
{
    /**
     * Entitlement Matrix – covers EVERY ABAC branch
     * ----------------------------------------------------
     *  City × Dataset × Entitlement type
     *  – DS-ALL   : Full dataset (buildings)
     *  – DS-AOI   : Polygon-restricted dataset access (buildings)
     *  – DS-BLD   : Hand-picked building GIDs (buildings)
     *  – TILES(A) : Polygon-restricted tile access (thermal_raster)
     *  – TILES(G) : Global tile access (thermal_raster)
     */
    public function run(): void
    {
        Entitlement::truncate();

        // Get the real Paris datasets
        $buildingDataset = Dataset::where('name', 'Paris Building Footprints BDTOPO 2025-Q1')->first();
        $thermalDataset = Dataset::where('name', 'Paris Thermal Imagery BOA 2023-Q4')->first();

        if (!$buildingDataset || !$thermalDataset) {
            $this->command->error('❌ Paris datasets not found! Run DatasetSeeder first.');
            return;
        }

        $entitlements = [
            // ──────────── Full Paris Access ────────────
            [
                'type' => 'DS-ALL',
                'dataset_id' => $buildingDataset->id,
                'aoi_geom' => null,
                'building_gids' => null,
                'download_formats' => ['csv', 'geojson', 'excel'],
                'expires_at' => now()->addYear(),
            ],
            [
                'type' => 'TILES',
                'dataset_id' => $thermalDataset->id,
                'aoi_geom' => $this->parisMetropolitanArea(),
                'building_gids' => null,
                'download_formats' => ['csv'],
                'expires_at' => now()->addYear(),
            ],

            // ──────────── Paris Central Districts (AOI) ────────────
            [
                'type' => 'DS-AOI',
                'dataset_id' => $buildingDataset->id,
                'aoi_geom' => $this->parisCentralDistricts(),
                'building_gids' => null,
                'download_formats' => ['csv', 'geojson'],
                'expires_at' => now()->addMonths(6),
            ],

            // ──────────── Paris Research Zone (Smaller AOI) ────────────
            [
                'type' => 'DS-AOI',
                'dataset_id' => $buildingDataset->id,
                'aoi_geom' => $this->parisResearchZone(),
                'building_gids' => null,
                'download_formats' => ['csv'],
                'expires_at' => now()->addMonths(3),
            ],

            // ──────────── Specific Building Access (Sample) ────────────
            [
                'type' => 'DS-BLD',
                'dataset_id' => $buildingDataset->id,
                'aoi_geom' => null,
                'building_gids' => ['BATIMENT_0001', 'BATIMENT_0002', 'BATIMENT_0003'], // Will be updated when real data is imported
                'download_formats' => ['csv'],
                'expires_at' => now()->addMonths(1),
            ],
        ];

        foreach ($entitlements as $entitlementData) {
            Entitlement::create($entitlementData);
        }

        $this->command->info('✅ Created ' . count($entitlements) . ' Paris-based entitlements');
    }

    /**
     * Create Paris metropolitan area polygon (approximate boundaries)
     */
    private function parisMetropolitanArea(): Polygon
    {
        // Approximate boundaries of Greater Paris (Île-de-France inner area)
        return new Polygon([
            new LineString([
                new Point(2.224, 48.815), // Southwest
                new Point(2.469, 48.815), // Southeast  
                new Point(2.469, 48.902), // Northeast
                new Point(2.224, 48.902), // Northwest
                new Point(2.224, 48.815), // Close polygon
            ])
        ]);
    }

    /**
     * Create Paris central districts polygon (1st-4th arrondissements)
     */
    private function parisCentralDistricts(): Polygon
    {
        // Central Paris area including Louvre, Châtelet, Marais
        return new Polygon([
            new LineString([
                new Point(2.325, 48.855), // Southwest
                new Point(2.365, 48.855), // Southeast
                new Point(2.365, 48.870), // Northeast  
                new Point(2.325, 48.870), // Northwest
                new Point(2.325, 48.855), // Close polygon
            ])
        ]);
    }

    /**
     * Create Paris research zone (smaller area for testing)
     */
    private function parisResearchZone(): Polygon
    {
        // Smaller research area around Notre-Dame/Île de la Cité
        return new Polygon([
            new LineString([
                new Point(2.340, 48.850), // Southwest
                new Point(2.355, 48.850), // Southeast
                new Point(2.355, 48.860), // Northeast
                new Point(2.340, 48.860), // Northwest
                new Point(2.340, 48.850), // Close polygon
            ])
        ]);
    }
}
