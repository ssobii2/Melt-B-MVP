<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Building;
use App\Models\Dataset;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\LineString;

class BuildingSeeder extends Seeder
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

        $buildingData = [
            // Buildings in Debrecen city center (within DS-AOI polygon)
            [
                'gid' => 'DEB_CTR_001',
                'geometry' => new Polygon([
                    new LineString([
                        new Point(47.5320, 21.6280),
                        new Point(47.5320, 21.6285),
                        new Point(47.5325, 21.6285),
                        new Point(47.5325, 21.6280),
                        new Point(47.5320, 21.6280),
                    ])
                ]),
                'thermal_loss_index_tli' => 85,
                'building_type_classification' => 'residential',
                'co2_savings_estimate' => 2500.50,
                'address' => 'Debrecen City Center, Building 1',
                'owner_operator_details' => 'Municipal Housing Authority',
                'cadastral_reference' => 'DEB-001-2024',
                'dataset_id' => $datasets->where('name', 'like', '%Debrecen%')->where('data_type', 'building-data')->first()?->id ?? 2,
                'last_analyzed_at' => now()->subDays(5),
                'before_renovation_tli' => 85,
                'after_renovation_tli' => 35,
            ],
            [
                'gid' => 'DEB_CTR_002',
                'geometry' => new Polygon([
                    new LineString([
                        new Point(47.5330, 21.6290),
                        new Point(47.5330, 21.6295),
                        new Point(47.5335, 21.6295),
                        new Point(47.5335, 21.6290),
                        new Point(47.5330, 21.6290),
                    ])
                ]),
                'thermal_loss_index_tli' => 72,
                'building_type_classification' => 'commercial',
                'co2_savings_estimate' => 1800.75,
                'address' => 'Debrecen City Center, Building 2',
                'owner_operator_details' => 'Local Business District',
                'cadastral_reference' => 'DEB-002-2024',
                'dataset_id' => $datasets->where('name', 'like', '%Debrecen%')->where('data_type', 'building-data')->first()?->id ?? 2,
                'last_analyzed_at' => now()->subDays(3),
                'before_renovation_tli' => 72,
                'after_renovation_tli' => 28,
            ],

            // Buildings outside Debrecen city center (not in DS-AOI)
            [
                'gid' => 'DEB_OUT_001',
                'geometry' => new Polygon([
                    new LineString([
                        new Point(47.5600, 21.6600),
                        new Point(47.5600, 21.6605),
                        new Point(47.5605, 21.6605),
                        new Point(47.5605, 21.6600),
                        new Point(47.5600, 21.6600),
                    ])
                ]),
                'thermal_loss_index_tli' => 45,
                'building_type_classification' => 'industrial',
                'co2_savings_estimate' => 5200.25,
                'address' => 'Debrecen Outskirts, Industrial Building',
                'owner_operator_details' => 'Manufacturing Corp',
                'cadastral_reference' => 'DEB-OUT-001-2024',
                'dataset_id' => $datasets->where('name', 'like', '%Debrecen%')->where('data_type', 'building-data')->first()?->id ?? 2,
                'last_analyzed_at' => now()->subDays(7),
                'before_renovation_tli' => 45,
                'after_renovation_tli' => 15,
            ],

            // Specific buildings for DS-BLD testing (Budapest)
            [
                'gid' => 'BLDG_001',
                'geometry' => new Polygon([
                    new LineString([
                        new Point(47.5000, 19.0450),
                        new Point(47.5000, 19.0455),
                        new Point(47.5005, 19.0455),
                        new Point(47.5005, 19.0450),
                        new Point(47.5000, 19.0450),
                    ])
                ]),
                'thermal_loss_index_tli' => 90,
                'building_type_classification' => 'residential',
                'co2_savings_estimate' => 3200.00,
                'address' => 'Budapest District V, Historic Building 1',
                'owner_operator_details' => 'Heritage Foundation',
                'cadastral_reference' => 'BUD-V-001-2024',
                'dataset_id' => $datasets->where('name', 'like', '%Budapest%')->where('data_type', 'building-data')->first()?->id ?? 4,
                'last_analyzed_at' => now()->subDays(2),
                'before_renovation_tli' => 90,
                'after_renovation_tli' => 40,
            ],
            [
                'gid' => 'BLDG_002',
                'geometry' => new Polygon([
                    new LineString([
                        new Point(47.5010, 19.0460),
                        new Point(47.5010, 19.0465),
                        new Point(47.5015, 19.0465),
                        new Point(47.5015, 19.0460),
                        new Point(47.5010, 19.0460),
                    ])
                ]),
                'thermal_loss_index_tli' => 78,
                'building_type_classification' => 'commercial',
                'co2_savings_estimate' => 2100.50,
                'address' => 'Budapest District V, Historic Building 2',
                'owner_operator_details' => 'Commercial District Management',
                'cadastral_reference' => 'BUD-V-002-2024',
                'dataset_id' => $datasets->where('name', 'like', '%Budapest%')->where('data_type', 'building-data')->first()?->id ?? 4,
                'last_analyzed_at' => now()->subDays(4),
                'before_renovation_tli' => 78,
                'after_renovation_tli' => 30,
            ],
            [
                'gid' => 'BLDG_003',
                'geometry' => new Polygon([
                    new LineString([
                        new Point(47.5020, 19.0470),
                        new Point(47.5020, 19.0475),
                        new Point(47.5025, 19.0475),
                        new Point(47.5025, 19.0470),
                        new Point(47.5020, 19.0470),
                    ])
                ]),
                'thermal_loss_index_tli' => 65,
                'building_type_classification' => 'residential',
                'co2_savings_estimate' => 1900.25,
                'address' => 'Budapest District V, Historic Building 3',
                'owner_operator_details' => 'Residential Housing Co-op',
                'cadastral_reference' => 'BUD-V-003-2024',
                'dataset_id' => $datasets->where('name', 'like', '%Budapest%')->where('data_type', 'building-data')->first()?->id ?? 4,
                'last_analyzed_at' => now()->subDays(1),
                'before_renovation_tli' => 65,
                'after_renovation_tli' => 25,
            ],

            // Buildings not in any specific entitlement
            [
                'gid' => 'NO_ACCESS_001',
                'geometry' => new Polygon([
                    new LineString([
                        new Point(47.4000, 19.0000),
                        new Point(47.4000, 19.0005),
                        new Point(47.4005, 19.0005),
                        new Point(47.4005, 19.0000),
                        new Point(47.4000, 19.0000),
                    ])
                ]),
                'thermal_loss_index_tli' => 55,
                'building_type_classification' => 'industrial',
                'co2_savings_estimate' => 4500.00,
                'address' => 'Remote Location, No Access Building',
                'owner_operator_details' => 'Private Company',
                'cadastral_reference' => 'REMOTE-001-2024',
                'dataset_id' => $datasets->where('name', 'like', '%Budapest%')->where('data_type', 'building-data')->first()?->id ?? 4,
                'last_analyzed_at' => now()->subDays(10),
                'before_renovation_tli' => 55,
                'after_renovation_tli' => 20,
            ],

            // Buildings in larger Debrecen area (for testing larger DS-AOI)
            [
                'gid' => 'DEB_LARGE_001',
                'geometry' => new Polygon([
                    new LineString([
                        new Point(47.5300, 21.6300),
                        new Point(47.5300, 21.6305),
                        new Point(47.5305, 21.6305),
                        new Point(47.5305, 21.6300),
                        new Point(47.5300, 21.6300),
                    ])
                ]),
                'thermal_loss_index_tli' => 38,
                'building_type_classification' => 'residential',
                'co2_savings_estimate' => 1200.00,
                'address' => 'Debrecen Extended Area, Building 1',
                'owner_operator_details' => 'Suburban Development',
                'cadastral_reference' => 'DEB-EXT-001-2024',
                'dataset_id' => $datasets->where('name', 'like', '%Debrecen%')->where('data_type', 'building-data')->first()?->id ?? 2,
                'last_analyzed_at' => now()->subDays(6),
                'before_renovation_tli' => 38,
                'after_renovation_tli' => 12,
            ],
        ];

        foreach ($buildingData as $building) {
            Building::create($building);
        }

        $this->command->info('✅ Created ' . count($buildingData) . ' buildings with spatial geometries for ABAC testing');
    }
}
