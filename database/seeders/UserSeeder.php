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
                'password' => Hash::make('Abcd1234'),
                'role' => 'admin',
                'api_key' => Str::random(64),
                'contact_info' => [
                    'department' => 'IT Administration',
                    'phone' => '+33 1 42 76 40 40',
                ],
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Paris Municipality',
                'email' => 'thermal@paris.fr',
                'password' => Hash::make('Abcd1234'),
                'role' => 'municipality',
                'api_key' => Str::random(64),
                'contact_info' => [
                    'organization' => 'Ville de Paris',
                    'department' => 'Direction de l\'Urbanisme',
                    'phone' => '+33 1 42 76 40 40',
                    'address' => 'Hôtel de Ville, Place de l\'Hôtel de Ville, 75004 Paris',
                ],
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Research Institute User',
                'email' => 'researcher@sorbonne.fr',
                'password' => Hash::make('Abcd1234'),
                'role' => 'researcher',
                'contact_info' => [
                    'organization' => 'Sorbonne Université',
                    'department' => 'Sciences de l\'Environnement',
                    'phone' => '+33 1 44 27 44 27',
                ],
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Energy Contractor',
                'email' => 'contractor@energieparis.fr',
                'password' => Hash::make('Abcd1234'),
                'role' => 'contractor',
                'api_key' => Str::random(64),
                'contact_info' => [
                    'company' => 'Énergie Paris Solutions SARL',
                    'phone' => '+33 1 45 67 89 01',
                    'services' => ['thermal-renovation', 'energy-audit'],
                ],
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Test User',
                'email' => 'user@test.com',
                'password' => Hash::make('Abcd1234'),
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
