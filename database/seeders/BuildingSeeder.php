<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Building;
use App\Models\Dataset;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\Point;

class BuildingSeeder extends Seeder
{
    /**
     * The goal is **not** to flood the DB with thousands of rows – we only need
     * a handful of footprints that let us verify every ABAC variant.
     *
     *  • Sample buildings with anomaly detection data included
     *  • Building GIDs are unique across datasets so DS-BLD can target them.
     *  • Anomaly detection fields populated with realistic test data.
     */
    public function run(): void
    {
        Building::truncate();

        $datasets = Dataset::all()->keyBy('name');

        // Check if we have the expected datasets
        if (!$datasets->has('Paris Building Anomalies Analysis 2025-Q1')) {
            $this->command->error('❌ Required Paris dataset not found! Run DatasetSeeder first.');
            return;
        }

        $records = [
            // ───────────────── Sample Paris Buildings (using anomaly dataset) ─────────────────
            [
                'gid' => 'PARIS_ANOMALY_001',
                'building_type_classification' => 'apartments',
                'geometry' => $this->rect(48.8566, 2.3522, 0.0004), // Near Notre-Dame
                'dataset_id' => $datasets['Paris Building Anomalies Analysis 2025-Q1']->id,
                'last_analyzed_at' => now(),
                'address' => '123 Rue de Rivoli, Paris',
                'co2_savings_estimate' => 500,
                'cadastral_reference' => 'PARIS_001_CAD',
                'owner_operator_details' => 'Copropriété Rivoli',
                // Anomaly detection fields
                'average_heatloss' => 174.98,
                'reference_heatloss' => 88.4,
                'heatloss_difference' => 86.58,
                'abs_heatloss_difference' => 86.58,
                'threshold' => 86.58,
                'is_anomaly' => false, // Right at threshold
                'confidence' => 1.0,
            ],
            [
                'gid' => 'PARIS_ANOMALY_002',
                'building_type_classification' => 'apartments',
                'geometry' => $this->rect(48.8570, 2.3530, 0.0004), // Near Louvre
                'dataset_id' => $datasets['Paris Building Anomalies Analysis 2025-Q1']->id,
                'last_analyzed_at' => now(),
                'address' => '456 Rue du Louvre, Paris',
                'co2_savings_estimate' => 600,
                'cadastral_reference' => 'PARIS_002_CAD',
                'owner_operator_details' => 'SCI Louvre Holdings',
                // Anomaly detection fields
                'average_heatloss' => 18.13,
                'reference_heatloss' => 88.4,
                'heatloss_difference' => -70.27,
                'abs_heatloss_difference' => 70.27,
                'threshold' => 86.58,
                'is_anomaly' => false,
                'confidence' => 0.36,
            ],
        ];

        foreach ($records as &$r) {
            $r['co2_savings_estimate'] = $r['co2_savings_estimate'] ?? rand(100,1200);
            $r['address'] = $r['address'] ?? 'Sample address';
            $r['cadastral_reference'] = $r['cadastral_reference'] ?? $r['gid'].'-CAD';
            $r['owner_operator_details'] = $r['owner_operator_details'] ?? 'Synthetic owner';
        }

        foreach ($records as $record) {
            Building::create($record);
        }

        $this->command->info('✅ Created ' . count($records) . ' sample buildings with anomaly detection data');
    }

    private function rect(float $lat, float $lon, float $size): Polygon
    {
        // Helper returns a square Polygon centred at (lat,lon)
        $half = $size / 2;
        $sw = new Point($lat - $half, $lon - $half);
        $se = new Point($lat - $half, $lon + $half);
        $ne = new Point($lat + $half, $lon + $half);
        $nw = new Point($lat + $half, $lon - $half);

        return new Polygon([
            new LineString([$sw, $se, $ne, $nw, $sw])
        ]);
    }
}
