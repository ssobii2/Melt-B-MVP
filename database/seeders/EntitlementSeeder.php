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

        foreach ($entitlements as $entitlementData) {
            // For DS-BLD type, check if entitlement with same type, dataset_id and building_gids exists
            if ($entitlementData['type'] === 'DS-BLD' && !empty($entitlementData['building_gids'])) {
                $existing = Entitlement::where('type', $entitlementData['type'])
                    ->where('dataset_id', $entitlementData['dataset_id'])
                    ->whereJsonContains('building_gids', $entitlementData['building_gids'])
                    ->first();
                    
                if ($existing) {
                    $existing->update($entitlementData);
                } else {
                    Entitlement::create($entitlementData);
                }
            }
            // For DS-AOI type, use simpler unique key without geometry comparison
            elseif ($entitlementData['type'] === 'DS-AOI') {
                $uniqueKey = [
                    'type' => $entitlementData['type'],
                    'dataset_id' => $entitlementData['dataset_id']
                ];
                
                // Find existing AOI entitlement and update, or create new one
                $existing = Entitlement::where($uniqueKey)->first();
                if ($existing) {
                    $existing->update($entitlementData);
                } else {
                    Entitlement::create($entitlementData);
                }
            }
            // For DS-ALL type, simple unique key
            else {
                $uniqueKey = [
                    'type' => $entitlementData['type'],
                    'dataset_id' => $entitlementData['dataset_id']
                ];
                
                Entitlement::updateOrCreate($uniqueKey, $entitlementData);
            }
        }

        $this->command->info('✅ Created/updated ' . count($entitlements) . ' anomaly detection entitlements (TILES removed)');
    }

    /**
     * Create Paris central districts polygon (1st-4th arrondissements)
     */
    private function parisCentralDistricts(): Polygon
    {
        // Central Paris area including Louvre, Châtelet, Marais (1st-4th arrondissements)
        // Testing: Point constructor might expect (latitude, longitude) instead
        return new Polygon([
            new LineString([
                new Point(48.850, 2.320), // Southwest: Latitude 48.850, Longitude 2.320
                new Point(48.850, 2.370), // Southeast: Latitude 48.850, Longitude 2.370
                new Point(48.875, 2.370), // Northeast: Latitude 48.875, Longitude 2.370
                new Point(48.875, 2.320), // Northwest: Latitude 48.875, Longitude 2.320
                new Point(48.850, 2.320), // Close polygon
            ])
        ]);
    }

    /**
     * Create Paris research zone (smaller area for testing)
     */
    private function parisResearchZone(): Polygon
    {
        // Northern Paris area around Montmartre (18th arrondissement)
        // Testing: Point constructor might expect (latitude, longitude) instead
        return new Polygon([
            new LineString([
                new Point(48.880, 2.330), // Southwest: Latitude 48.880, Longitude 2.330
                new Point(48.880, 2.360), // Southeast: Latitude 48.880, Longitude 2.360
                new Point(48.900, 2.360), // Northeast: Latitude 48.900, Longitude 2.360
                new Point(48.900, 2.330), // Northwest: Latitude 48.900, Longitude 2.330
                new Point(48.880, 2.330), // Close polygon
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
