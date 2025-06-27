<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            DatasetSeeder::class,
            EntitlementSeeder::class,
            UserEntitlementSeeder::class,
            // BuildingSeeder::class, // Commented out - using real data instead
        ]);
    }
}
