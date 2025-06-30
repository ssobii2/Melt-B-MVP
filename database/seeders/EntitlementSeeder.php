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
     * Entitlement Matrix – Updated for Anomaly Detection
     * ----------------------------------------------------
     *  Dataset × Entitlement type (TILES removed per REFACTOR.md)
     *  – DS-ALL   : Full dataset access (building anomalies)
     *  – DS-AOI   : Polygon-restricted dataset access (building anomalies)
     *  – DS-BLD   : Hand-picked building GIDs (building anomalies)
     */
    public function run(): void
    {
        Entitlement::truncate();

        // Get the anomaly detection datasets
        $parisDataset = Dataset::where('name', 'Paris Building Anomalies Analysis 2025-Q1')->first();

        if (!$parisDataset) {
            $this->command->error('❌ Anomaly detection datasets not found! Run DatasetSeeder first.');
            return;
        }

        $entitlements = [
            // ──────────── Full Paris Anomaly Access ────────────
            [
                'type' => 'DS-ALL',
                'dataset_id' => $parisDataset->id,
                'aoi_geom' => null,
                'building_gids' => null,
                'download_formats' => ['csv', 'geojson', 'excel'],
                'expires_at' => now()->addYear(),
            ],

            // ──────────── Paris Central Districts (AOI) ────────────
            [
                'type' => 'DS-AOI',
                'dataset_id' => $parisDataset->id,
                'aoi_geom' => $this->parisCentralDistricts(),
                'building_gids' => null,
                'download_formats' => ['csv', 'geojson'],
                'expires_at' => now()->addMonths(6),
            ],

            // ──────────── Paris Research Zone (Smaller AOI) ────────────
            [
                'type' => 'DS-AOI',
                'dataset_id' => $parisDataset->id,
                'aoi_geom' => $this->parisResearchZone(),
                'building_gids' => null,
                'download_formats' => ['csv'],
                'expires_at' => now()->addMonths(3),
            ],

            // ──────────── Specific Building Access (Sample) ────────────
            [
                'type' => 'DS-BLD',
                'dataset_id' => $parisDataset->id,
                'aoi_geom' => null,
                'building_gids' => ['BATIMENT0000000000157984', 'BATIMENT0000000000157989', 'BATIMENT0000000000157995'],
                'download_formats' => ['csv'],
                'expires_at' => now()->addMonths(1),
            ],
        ];

        foreach ($entitlements as $entitlementData) {
            Entitlement::create($entitlementData);
        }

        $this->command->info('✅ Created ' . count($entitlements) . ' anomaly detection entitlements (TILES removed)');
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
