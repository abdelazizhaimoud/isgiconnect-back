<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User\User;
use App\Models\User\Role;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $studentRole = Role::where('slug', 'student')->first();

        if (!$studentRole) {
            $this->command->error('Student role not found. Please run RolePermissionSeeder first.');
            return;
        }

        $students = [
            [
                'name' => 'Alice Cooper',
                'username' => 'alicec',
                'email' => 'alice.cooper@student.test',
            ],
            [
                'name' => 'Bob Miller',
                'username' => 'bobm',
                'email' => 'bob.miller@student.test',
            ],
        ];

        foreach ($students as $studentData) {
            User::create([
                'name' => $studentData['name'],
                'username' => $studentData['username'],
                'email' => $studentData['email'],
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
                'role' => 'student', // Set the role directly
            ]);
        }

        $this->command->info('✓ Created ' . count($students) . ' Student users');
    }
}
