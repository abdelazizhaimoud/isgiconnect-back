<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User\User;
use App\Models\User\Role;
use Illuminate\Support\Facades\Hash;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companyRole = Role::where('slug', 'company')->first();

        if (!$companyRole) {
            $this->command->error('Company role not found. Please run RolePermissionSeeder first.');
            return;
        }

        $companies = [
            [
                'name' => 'Tech Solutions Inc.',
                'username' => 'techsolutions',
                'email' => 'contact@techsolutions.test',
            ],
            [
                'name' => 'Innovate Corp.',
                'username' => 'innovatecorp',
                'email' => 'hr@innovatecorp.test',
            ],
        ];

        foreach ($companies as $companyData) {
            User::create([
                'name' => $companyData['name'],
                'username' => $companyData['username'],
                'email' => $companyData['email'],
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
                'role' => 'company', // Set the role directly
            ]);
        }

        $this->command->info('✓ Created ' . count($companies) . ' Company users');
    }
}
