<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User\Role;
use App\Models\User\Permission;
use App\Models\User\User;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Permissions
        $permissions = $this->createPermissions();
        
        // Create Roles
        $roles = $this->createRoles();
        
        // Assign Permissions to Roles
        $this->assignPermissionsToRoles($roles, $permissions);

        // Create Admin User
        User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
    }

    /**
     * Create all permissions.
     */
    private function createPermissions(): array
    {
        $permissionData = [
            // User Management
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'group' => 'Admin'],

            // Job Posting Management
            ['name' => 'Create Job Postings', 'slug' => 'job-postings.create', 'group' => 'Jobs'],
            ['name' => 'View Job Postings', 'slug' => 'job-postings.view', 'group' => 'Jobs'],
            ['name' => 'Update Own Job Postings', 'slug' => 'job-postings.update_own', 'group' => 'Jobs'],
            ['name' => 'Delete Own Job Postings', 'slug' => 'job-postings.delete_own', 'group' => 'Jobs'],

            // Application Management
            ['name' => 'Apply to Jobs', 'slug' => 'applications.create', 'group' => 'Applications'],
            ['name' => 'View Own Applications', 'slug' => 'applications.view_own', 'group' => 'Applications'],
            ['name' => 'View Applicants for Own Jobs', 'slug' => 'applications.view_applicants', 'group' => 'Applications'],
        ];

        $permissions = [];
        foreach ($permissionData as $data) {
            $permissions[] = Permission::create($data);
        }

        return $permissions;
    }

    /**
     * Create all roles.
     */
    private function createRoles(): array
    {
        $roleData = [
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Administrative access to manage content and users',
                'is_default' => false,
                'level' => 80,
            ],
            [
                'name' => 'Company',
                'slug' => 'company',
                'description' => 'Represents a company that can post jobs.',
                'is_default' => false,
                'level' => 20,
            ],
            [
                'name' => 'Student',
                'slug' => 'student',
                'description' => 'Represents a student who can apply for jobs.',
                'is_default' => true,
                'level' => 10,
            ],
        ];

        $roles = [];
        foreach ($roleData as $data) {
            $roles[] = Role::create($data);
        }

        return $roles;
    }

    /**
     * Assign permissions to roles.
     */
    private function assignPermissionsToRoles(array $roles, array $permissions): void
    {
        $rolesBySlug = collect($roles)->keyBy('slug');
        $permissionsBySlug = collect($permissions)->keyBy('slug');

        // Admin - All permissions
        if (isset($rolesBySlug['admin'])) {
            $rolesBySlug['admin']->permissions()->sync($permissions);
        }

        // Company Permissions
        if (isset($rolesBySlug['company'])) {
            $companyPermissions = [
                $permissionsBySlug['job-postings.create'],
                $permissionsBySlug['job-postings.view'],
                $permissionsBySlug['job-postings.update_own'],
                $permissionsBySlug['job-postings.delete_own'],
                $permissionsBySlug['applications.view_applicants'],
            ];
            $rolesBySlug['company']->permissions()->sync($companyPermissions);
        }

        // Student Permissions
        if (isset($rolesBySlug['student'])) {
            $studentPermissions = [
                $permissionsBySlug['job-postings.view'],
                $permissionsBySlug['applications.create'],
                $permissionsBySlug['applications.view_own'],
            ];
            $rolesBySlug['student']->permissions()->sync($studentPermissions);
        }
    }
}