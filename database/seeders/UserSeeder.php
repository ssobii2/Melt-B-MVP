<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'System Administrator',
                'email' => 'admin@melt-b.com',
                'password' => Hash::make('admin123!'),
                'role' => 'admin',
                'api_key' => Str::random(64),
                'contact_info' => [
                    'department' => 'IT Administration',
                    'phone' => '+36 1 234 5678',
                ],
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Debrecen Municipality',
                'email' => 'thermal@debrecen.hu',
                'password' => Hash::make('municipality123!'),
                'role' => 'municipality',
                'api_key' => Str::random(64),
                'contact_info' => [
                    'organization' => 'City of Debrecen',
                    'department' => 'Urban Development',
                    'phone' => '+36 52 511 400',
                    'address' => 'Kálvin tér 9, 4026 Debrecen',
                ],
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Research Institute User',
                'email' => 'researcher@university.hu',
                'password' => Hash::make('researcher123!'),
                'role' => 'researcher',
                'contact_info' => [
                    'organization' => 'University of Debrecen',
                    'department' => 'Environmental Sciences',
                    'phone' => '+36 52 512 900',
                ],
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Energy Contractor',
                'email' => 'contractor@energytech.hu',
                'password' => Hash::make('contractor123!'),
                'role' => 'contractor',
                'api_key' => Str::random(64),
                'contact_info' => [
                    'company' => 'EnergyTech Solutions Kft.',
                    'phone' => '+36 1 345 6789',
                    'services' => ['thermal-renovation', 'energy-audit'],
                ],
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Test User',
                'email' => 'user@test.com',
                'password' => Hash::make('user123!'),
                'role' => 'user',
                'contact_info' => null,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $userData) {
            User::create($userData);
        }
    }
}
