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

            // Paris municipality – full building dataset access (Paris only)
            'thermal@paris.fr' => [
                ['type' => 'DS-ALL',  'dataset' => 'Paris Building Anomalies Analysis 2025-Q1'],
            ],

            // Researcher – specific Paris buildings access (200 buildings)
            'researcher@sorbonne.fr' => [
                ['type' => 'DS-BLD', 'dataset' => 'Paris Building Anomalies Analysis 2025-Q1', 'index' => 0], // First DS-BLD entitlement
            ],

            // Contractor gets limited building access (150 buildings)
            'contractor@energieparis.fr' => [
                ['type' => 'DS-BLD', 'dataset' => 'Paris Building Anomalies Analysis 2025-Q1', 'index' => 1], // Second DS-BLD entitlement
            ],

            // Test user gets basic access (50 buildings)
            'user@test.com' => [
                ['type' => 'DS-BLD', 'dataset' => 'Paris Building Anomalies Analysis 2025-Q1', 'index' => 2], // Third DS-BLD entitlement
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
                    DB::table('user_entitlements')->insertOrIgnore([
                        'user_id' => $user->id,
                        'entitlement_id' => $entitlement->id,
                        'created_at' => now(),
                    ]);
                }

                $this->command->info("✅ Assigned ALL entitlements to {$user->name}");
            } else {
                // Assign specific entitlement types
                foreach ($entitlementTypes as $spec) {
                    // Allow two formats: simple string ('DS-BLD') or array(['type'=>'DS-BLD','dataset'=>'Name','index'=>0])
                    if (is_array($spec)) {
                        $type  = $spec['type'];
                        $dsName = $spec['dataset'] ?? null;
                        $index = $spec['index'] ?? null;
                        $datasetId = $dsName && isset($datasets[$dsName]) ? $datasets[$dsName]->id : null;

                        $typeEntitlements = $entitlements->where('type', $type)
                            ->when($datasetId, function ($collection) use ($datasetId) {
                                return $collection->where('dataset_id', $datasetId);
                            })
                            ->where(function ($entitlement) {
                                return $entitlement->expires_at === null || $entitlement->expires_at > now();
                            });
                        
                        // If index is specified, get only that specific entitlement
                        if ($index !== null) {
                            $typeEntitlements = $typeEntitlements->values();
                            if (isset($typeEntitlements[$index])) {
                                $typeEntitlements = collect([$typeEntitlements[$index]]);
                            } else {
                                $typeEntitlements = collect();
                            }
                        }
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
                            DB::table('user_entitlements')->insertOrIgnore([
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
