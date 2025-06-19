<?php

namespace Database\Seeders;

use App\Models\User\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Core data for Job Board
            RolePermissionSeeder::class,

            // User data
            CompanySeeder::class,
            StudentSeeder::class,

            // Application data
            JobPostingSeeder::class,
            ApplicationSeeder::class,
            SettingsSeeder::class,
            ContentTypeSeeder::class,
            
            // Demo data
            DemoUsersSeeder::class,
            ConversationSeeder::class,
            ConversationParticipantSeeder::class,
            MessageSeeder::class,
            // DemoMediaSeeder::class,
            // DemoContentSeeder::class,
            // PostSeeder::class,
            
            // Test data (only in local/development)
            // TestDataSeeder::class,
        ]);

        // Create a default admin user if not exists
        if (!User::where('email', 'admin@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Admin User',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'status' => 'active',
                'role' => 'admin',
                'email_verified_at' => now(),
            ])->assignRole('admin');
        }
    }
}
