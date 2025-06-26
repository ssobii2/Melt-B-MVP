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
     *  • 3 × cities × 4 buildings  = 12 rows total.
     *  • Building GIDs are unique across datasets so DS-BLD can target them.
     *  • TLI values cover low-to-very-high ranges (green→red legend).
     */
    public function run(): void
    {
        Building::truncate();

        $datasets = Dataset::all()->keyBy('name');

        $records = [
            // ───────────────── Debrecen (approx 47.533 N, 21.63 E) ─────────────────
            [
                'gid' => 'DEB_001',
                'thermal_loss_index_tli' => 15, // Low
                'building_type_classification' => 'residential',
                'geometry' => $this->rect(47.5330, 21.6290, 0.0005),
                'dataset_id' => $datasets['Building Data 2024-Q4 Debrecen']->id,
                'last_analyzed_at' => now(),
                'address' => '123 Main St, Debrecen',
                'co2_savings_estimate' => 100,
                'cadastral_reference' => '123456789',
                'owner_operator_details' => 'John Doe',
                'renovation_tli' => 10,
            ],
            [
                'gid' => 'DEB_002',
                'thermal_loss_index_tli' => 45, // Medium
                'building_type_classification' => 'commercial',
                'geometry' => $this->rect(47.5335, 21.6295, 0.0005),
                'dataset_id' => $datasets['Building Data 2024-Q4 Debrecen']->id,
                'last_analyzed_at' => now(),
                'address' => '456 Elm St, Debrecen',
                'co2_savings_estimate' => 200,
                'cadastral_reference' => '987654321',
                'owner_operator_details' => 'Jane Smith',
                'renovation_tli' => 20,
            ],
            [
                'gid' => 'DEB_003',
                'thermal_loss_index_tli' => 70, // Medium-High
                'building_type_classification' => 'public',
                'geometry' => $this->rect(47.5340, 21.6300, 0.0005),
                'dataset_id' => $datasets['Building Data 2024-Q4 Debrecen']->id,
                'last_analyzed_at' => now(),
                'address' => '789 Oak St, Debrecen',
                'co2_savings_estimate' => 300,
                'cadastral_reference' => '567890123',
                'owner_operator_details' => 'Bob Johnson',
                'renovation_tli' => 30,
            ],
            [
                'gid' => 'DEB_004',
                'thermal_loss_index_tli' => 92, // Very High
                'building_type_classification' => 'public',
                'geometry' => $this->rect(47.5345, 21.6305, 0.0005),
                'dataset_id' => $datasets['Building Data 2024-Q4 Debrecen']->id,
                'last_analyzed_at' => now(),
                'address' => '101 Pine St, Debrecen',
                'co2_savings_estimate' => 400,
                'cadastral_reference' => '345678901',
                'owner_operator_details' => 'Alice Brown',
                'renovation_tli' => 40,
            ],

            // ───────────────── Budapest district V (47.4979 N, 19.0402 E) ─────────────────
            [
                'gid' => 'BUD_001',
                'thermal_loss_index_tli' => 22,
                'building_type_classification' => 'commercial',
                'geometry' => $this->rect(47.4975, 19.0400, 0.0004),
                'dataset_id' => $datasets['Building Data 2024-Q3 Budapest District V']->id,
                'last_analyzed_at' => now(),
                'address' => '123 Main St, Budapest',
                'co2_savings_estimate' => 500,
                'cadastral_reference' => '123456789',
                'owner_operator_details' => 'John Doe',
                'renovation_tli' => 50,
            ],
            [
                'gid' => 'BUD_002',
                'thermal_loss_index_tli' => 55,
                'building_type_classification' => 'public',
                'geometry' => $this->rect(47.4980, 19.0410, 0.0004),
                'dataset_id' => $datasets['Building Data 2024-Q3 Budapest District V']->id,
                'last_analyzed_at' => now(),
                'address' => '456 Elm St, Budapest',
                'co2_savings_estimate' => 600,
                'cadastral_reference' => '987654321',
                'owner_operator_details' => 'Jane Smith',
                'renovation_tli' => 60,
            ],
            [
                'gid' => 'BUD_003',
                'thermal_loss_index_tli' => 78,
                'building_type_classification' => 'public',
                'geometry' => $this->rect(47.4985, 19.0415, 0.0004),
                'dataset_id' => $datasets['Building Data 2024-Q3 Budapest District V']->id,
                'last_analyzed_at' => now(),
                'address' => '789 Oak St, Budapest',
                'co2_savings_estimate' => 700,
                'cadastral_reference' => '567890123',
                'owner_operator_details' => 'Bob Johnson',
                'renovation_tli' => 70,
            ],
            [
                'gid' => 'BUD_004',
                'thermal_loss_index_tli' => 95,
                'building_type_classification' => 'industrial',
                'geometry' => $this->rect(47.4990, 19.0420, 0.0004),
                'dataset_id' => $datasets['Building Data 2024-Q3 Budapest District V']->id,
                'last_analyzed_at' => now(),
                'address' => '101 Pine St, Budapest',
                'co2_savings_estimate' => 800,
                'cadastral_reference' => '345678901',
                'owner_operator_details' => 'Alice Brown',
                'renovation_tli' => 80,
            ],

            // ───────────────── Copenhagen (55.676 N, 12.568 E) ─────────────────
            [
                'gid' => 'CPH_001',
                'thermal_loss_index_tli' => 28,
                'building_type_classification' => 'residential',
                'geometry' => $this->rect(55.6760, 12.5675, 0.0006),
                'dataset_id' => $datasets['Building Data 2023-Q4 Copenhagen']->id,
                'last_analyzed_at' => now(),
                'address' => '123 Main St, Copenhagen',
                'co2_savings_estimate' => 900,
                'cadastral_reference' => '123456789',
                'owner_operator_details' => 'John Doe',
                'renovation_tli' => 90,
            ],
            [
                'gid' => 'CPH_002',
                'thermal_loss_index_tli' => 48,
                'building_type_classification' => 'residential',
                'geometry' => $this->rect(55.6765, 12.5680, 0.0006),
                'dataset_id' => $datasets['Building Data 2023-Q4 Copenhagen']->id,
                'last_analyzed_at' => now(),
                'address' => '456 Elm St, Copenhagen',
                'co2_savings_estimate' => 1000,
                'cadastral_reference' => '987654321',
                'owner_operator_details' => 'Jane Smith',
                'renovation_tli' => 100,
            ],
            [
                'gid' => 'CPH_003',
                'thermal_loss_index_tli' => 66,
                'building_type_classification' => 'commercial',
                'geometry' => $this->rect(55.6770, 12.5685, 0.0006),
                'dataset_id' => $datasets['Building Data 2023-Q4 Copenhagen']->id,
                'last_analyzed_at' => now(),
                'address' => '789 Oak St, Copenhagen',
                'co2_savings_estimate' => 1100,
                'cadastral_reference' => '567890123',
                'owner_operator_details' => 'Bob Johnson',
                'renovation_tli' => 110,
            ],
            [
                'gid' => 'CPH_004',
                'thermal_loss_index_tli' => 88,
                'building_type_classification' => 'public',
                'geometry' => $this->rect(55.6775, 12.5690, 0.0006),
                'dataset_id' => $datasets['Building Data 2023-Q4 Copenhagen']->id,
                'last_analyzed_at' => now(),
                'address' => '101 Pine St, Copenhagen',
                'co2_savings_estimate' => 1200,
                'cadastral_reference' => '345678901',
                'owner_operator_details' => 'Alice Brown',
                'renovation_tli' => 120,
            ],
        ];

        foreach ($records as &$r) {
            $r['co2_savings_estimate'] = $r['co2_savings_estimate'] ?? rand(100,1200);
            $r['address'] = $r['address'] ?? 'Sample address';
            $r['cadastral_reference'] = $r['cadastral_reference'] ?? $r['gid'].'-CAD';
            $r['owner_operator_details'] = $r['owner_operator_details'] ?? 'Synthetic owner';
            $baseTli = $r['thermal_loss_index_tli'];
            $r['before_renovation_tli'] = $baseTli + rand(5,25);
            $r['after_renovation_tli']  = max(0, $baseTli - rand(5,25));
            // Remove deprecated key that no longer exists in the schema
            unset($r['renovation_tli']);
        }

        foreach ($records as $rec) {
            Building::create($rec);
        }

        $this->command->info('🏢 Seeded '.count($records).' synthetic building records');
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
