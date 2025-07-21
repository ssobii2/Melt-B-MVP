<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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
        // Remove truncate to allow duplicate protection
        // Entitlement::truncate();

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
                'download_formats' => ['csv', 'geojson'],
                'expires_at' => now()->addYear(),
            ],

            // ──────────── Test User AOI (Working coordinates) ────────────
            [
                'type' => 'DS-AOI',
                'dataset_id' => $parisDataset->id,
                'aoi_geom' => $this->testUserAOI(),
                'building_gids' => null,
                'download_formats' => ['csv', 'geojson'],
                'expires_at' => now()->addMonths(6),
            ],

            // ──────────── Contractor AOI (Working coordinates) ────────────
            [
                'type' => 'DS-AOI',
                'dataset_id' => $parisDataset->id,
                'aoi_geom' => $this->contractorAOI(),
                'building_gids' => null,
                'download_formats' => ['csv'],
                'expires_at' => now()->addMonths(3),
            ],

            // ──────────── Researcher Building Access (200 buildings) ────────────
            [
                'type' => 'DS-BLD',
                'dataset_id' => $parisDataset->id,
                'aoi_geom' => null,
                'building_gids' => $this->getResearcherBuildingGids(),
                'download_formats' => ['csv', 'geojson'],
                'expires_at' => now()->addMonths(6),
            ],

            // ──────────── Contractor Building Access (150 buildings) ────────────
            [
                'type' => 'DS-BLD',
                'dataset_id' => $parisDataset->id,
                'aoi_geom' => null,
                'building_gids' => $this->getContractorBuildingGids(),
                'download_formats' => ['csv'],
                'expires_at' => now()->addMonths(3),
            ],

            // ──────────── Test User Building Access (50 buildings) ────────────
            [
                'type' => 'DS-BLD',
                'dataset_id' => $parisDataset->id,
                'aoi_geom' => null,
                'building_gids' => $this->getTestUserBuildingGids(),
                'download_formats' => ['csv'],
                'expires_at' => now()->addMonths(1),
            ],
        ];

        // Clear existing user entitlements and entitlements to avoid duplicates
        DB::table('user_entitlements')
            ->whereIn('entitlement_id', function($query) use ($parisDataset) {
                $query->select('id')
                      ->from('entitlements')
                      ->where('dataset_id', $parisDataset->id);
            })
            ->delete();
        
        Entitlement::where('dataset_id', $parisDataset->id)->delete();
        
        foreach ($entitlements as $entitlementData) {
            Entitlement::create($entitlementData);
        }

        $this->command->info('✅ Created/updated ' . count($entitlements) . ' anomaly detection entitlements (TILES removed)');
    }

    /**
     * Create test user AOI polygon (verified to contain buildings)
     */
    private function testUserAOI(): Polygon
    {
        // AOI coordinates that contain actual buildings for testing
        return new Polygon([
            new LineString([
                new Point(48.825, 2.245), // Southwest: Latitude 48.825, Longitude 2.245
                new Point(48.825, 2.270), // Southeast: Latitude 48.825, Longitude 2.270
                new Point(48.835, 2.270), // Northeast: Latitude 48.835, Longitude 2.270
                new Point(48.835, 2.245), // Northwest: Latitude 48.835, Longitude 2.245
                new Point(48.825, 2.245), // Close polygon
            ])
        ]);
    }

    /**
     * Create contractor AOI polygon (verified to contain buildings, separate from test user)
     */
    private function contractorAOI(): Polygon
    {
        // AOI coordinates north of test user area that contain actual buildings
        return new Polygon([
            new LineString([
                new Point(48.835, 2.245), // Southwest: Latitude 48.835, Longitude 2.245
                new Point(48.835, 2.270), // Southeast: Latitude 48.835, Longitude 2.270
                new Point(48.845, 2.270), // Northeast: Latitude 48.845, Longitude 2.270
                new Point(48.845, 2.245), // Northwest: Latitude 48.845, Longitude 2.245
                new Point(48.835, 2.245), // Close polygon
            ])
        ]);
    }

    /**
     * Get building GIDs for researcher access (200 buildings)
     */
    private function getResearcherBuildingGids(): array
    {
        return \App\Models\Building::limit(200)->pluck('gid')->toArray();
    }

    /**
     * Get building GIDs for contractor access (150 buildings)
     */
    private function getContractorBuildingGids(): array
    {
        return \App\Models\Building::skip(200)->limit(150)->pluck('gid')->toArray();
    }

    /**
     * Get building GIDs for test user access (50 buildings)
     */
    private function getTestUserBuildingGids(): array
    {
        return \App\Models\Building::skip(350)->limit(50)->pluck('gid')->toArray();
    }
}
