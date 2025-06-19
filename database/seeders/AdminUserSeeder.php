<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User\User;
use App\Models\User\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin
        $this->createSuperAdmin();
        
        // Create Regular Admin
        $this->createAdmin();
        
        // Create Editor
        $this->createEditor();
    }

    /**
     * Create Super Admin user.
     */
    private function createSuperAdmin(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@hackathon.test',
            'password' => Hash::make('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Create profile
        $superAdmin->profile()->create([
            'first_name' => 'Super',
            'last_name' => 'Administrator',
            'bio' => 'System Super Administrator with full access to all features and settings.',
            'timezone' => 'UTC',
            'language' => 'en',
            'preferences' => [
                'theme' => 'dark',
                'notifications' => [
                    'email_notifications' => true,
                    'push_notifications' => true,
                    'security_alerts' => true,
                    'system_notifications' => true,
                ],
                'dashboard' => [
                    'show_quick_stats' => true,
                    'show_recent_activities' => true,
                    'items_per_page' => 25,
                ],
            ],
        ]);

        // Assign Super Admin role
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        if ($superAdminRole) {
            $superAdmin->assignRole($superAdminRole);
        }

        $this->command->info('✓ Super Admin created: superadmin@hackathon.test / password');
    }

    /**
     * Create Admin user.
     */
    private function createAdmin(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@hackathon.test',
            'password' => Hash::make('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Create profile
        $admin->profile()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'bio' => 'System Administrator responsible for managing users, content, and system settings.',
            'timezone' => 'UTC',
            'language' => 'en',
            'preferences' => [
                'theme' => 'light',
                'notifications' => [
                    'email_notifications' => true,
                    'push_notifications' => true,
                    'security_alerts' => true,
                    'system_notifications' => true,
                ],
                'dashboard' => [
                    'show_quick_stats' => true,
                    'show_recent_activities' => true,
                    'items_per_page' => 20,
                ],
            ],
        ]);

        // Assign Admin role
        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole) {
            $admin->assignRole($adminRole);
        }

        $this->command->info('✓ Admin created: admin@hackathon.test / password');
    }

    /**
     * Create Editor user.
     */
    private function createEditor(): void
    {
        $editor = User::create([
            'name' => 'Content Editor',
            'email' => 'editor@hackathon.test',
            'password' => Hash::make('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Create profile
        $editor->profile()->create([
            'first_name' => 'Content',
            'last_name' => 'Editor',
            'bio' => 'Content Editor responsible for managing and moderating content, categories, and user comments.',
            'timezone' => 'UTC',
            'language' => 'en',
            'preferences' => [
                'theme' => 'light',
                'notifications' => [
                    'email_notifications' => true,
                    'push_notifications' => false,
                    'content_updates' => true,
                    'comment_notifications' => true,
                ],
                'editor' => [
                    'auto_save' => true,
                    'show_word_count' => true,
                    'default_status' => 'draft',
                ],
            ],
        ]);

        // Assign Editor role
        $editorRole = Role::where('slug', 'editor')->first();
        if ($editorRole) {
            $editor->assignRole($editorRole);
        }

        $this->command->info('✓ Editor created: editor@hackathon.test / password');
    }
}