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

            // Municipality user gets DS-ALL and DS-AOI for their city
            'thermal@debrecen.hu' => [
                'DS-ALL',
                'DS-AOI', // Both AOI entitlements
            ],

            // Researcher gets specific building access and some AOI
            'researcher@university.hu' => [
                'DS-BLD', // Specific buildings in Budapest
                'DS-AOI', // One AOI for comparison
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
                foreach ($entitlementTypes as $type) {
                    $typeEntitlements = $entitlements->where('type', $type)
                        ->where(function ($entitlement) {
                            return $entitlement->expires_at === null || $entitlement->expires_at > now();
                        });

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

                $this->command->info("✅ Assigned {$assignedCount} entitlements to {$user->name} (" . implode(', ', $entitlementTypes) . ")");
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
