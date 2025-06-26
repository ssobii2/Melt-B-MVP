<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Entitlement;
use Illuminate\Support\Facades\DB;

class UserEntitlementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $entitlements = Entitlement::all();
        $datasets = \App\Models\Dataset::all()->keyBy('name');

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');
            return;
        }

        if ($entitlements->isEmpty()) {
            $this->command->warn('No entitlements found. Please run EntitlementSeeder first.');
            return;
        }

        // Define user-entitlement assignments for testing
        $assignments = [
            // Admin gets all entitlements (full access)
            'admin@melt-b.com' => ['all'],

            // Debrecen municipality – full building dataset + full raster tiles (Debrecen only)
            'thermal@debrecen.hu' => [
                ['type' => 'DS-ALL',  'dataset' => 'Building Data 2024-Q4 Debrecen'],
                ['type' => 'TILES',   'dataset' => 'Thermal Raster 2024-Q4 Debrecen'],
            ],

            // Researcher – specific Budapest buildings + Copenhagen AOI only (no Debrecen access)
            'researcher@university.hu' => [
                ['type' => 'DS-BLD', 'dataset' => 'Building Data 2024-Q3 Budapest District V'],
                ['type' => 'DS-AOI', 'dataset' => 'Building Data 2023-Q4 Copenhagen'],
            ],

            // Contractor gets limited building access
            'contractor@energytech.hu' => [
                'DS-BLD', // Specific buildings only
            ],

            // Test user gets TILES access only
            'user@test.com' => [
                'TILES', // Map tiles access
            ],
        ];

        foreach ($assignments as $email => $entitlementTypes) {
            $user = $users->where('email', $email)->first();

            if (!$user) {
                $this->command->warn("User with email {$email} not found. Skipping...");
                continue;
            }

            if (in_array('all', $entitlementTypes)) {
                // Assign all non-expired entitlements to admin
                $userEntitlements = $entitlements->filter(function ($entitlement) {
                    return $entitlement->expires_at === null || $entitlement->expires_at > now();
                });

                foreach ($userEntitlements as $entitlement) {
                    DB::table('user_entitlements')->insert([
                        'user_id' => $user->id,
                        'entitlement_id' => $entitlement->id,
                        'created_at' => now(),
                    ]);
                }

                $this->command->info("✅ Assigned ALL entitlements to {$user->name}");
            } else {
                // Assign specific entitlement types
                foreach ($entitlementTypes as $spec) {
                    // Allow two formats: simple string ('DS-BLD') or array(['type'=>'DS-BLD','dataset'=>'Name'])
                    if (is_array($spec)) {
                        $type  = $spec['type'];
                        $dsName = $spec['dataset'] ?? null;
                        $datasetId = $dsName && isset($datasets[$dsName]) ? $datasets[$dsName]->id : null;

                        $typeEntitlements = $entitlements->where('type', $type)
                            ->when($datasetId, function ($collection) use ($datasetId) {
                                return $collection->where('dataset_id', $datasetId);
                            })
                            ->where(function ($entitlement) {
                                return $entitlement->expires_at === null || $entitlement->expires_at > now();
                            });
                    } else {
                        $type = $spec;
                        $typeEntitlements = $entitlements->where('type', $type)
                            ->where(function ($entitlement) {
                                return $entitlement->expires_at === null || $entitlement->expires_at > now();
                            });
                    }

                    foreach ($typeEntitlements as $entitlement) {
                        // Check if already assigned to avoid duplicates
                        $exists = DB::table('user_entitlements')
                            ->where('user_id', $user->id)
                            ->where('entitlement_id', $entitlement->id)
                            ->exists();

                        if (!$exists) {
                            DB::table('user_entitlements')->insert([
                                'user_id' => $user->id,
                                'entitlement_id' => $entitlement->id,
                                'created_at' => now(),
                            ]);
                        }
                    }
                }

                $assignedCount = DB::table('user_entitlements')
                    ->where('user_id', $user->id)
                    ->count();

                $this->command->info("✅ Assigned {$assignedCount} entitlements to {$user->name}");
            }
        }

        // Summary
        $totalAssignments = DB::table('user_entitlements')->count();
        $this->command->info("🎉 Total user-entitlement assignments created: {$totalAssignments}");

        // Display assignment summary
        $this->command->info("\n📊 Assignment Summary:");
        foreach ($users as $user) {
            $userEntitlements = DB::table('user_entitlements')
                ->join('entitlements', 'user_entitlements.entitlement_id', '=', 'entitlements.id')
                ->where('user_entitlements.user_id', $user->id)
                ->select('entitlements.type')
                ->get()
                ->pluck('type')
                ->unique()
                ->values()
                ->toArray();

            if (!empty($userEntitlements)) {
                $this->command->line("  • {$user->name}: " . implode(', ', $userEntitlements));
            }
        }
    }
}
