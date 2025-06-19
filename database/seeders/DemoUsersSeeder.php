<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User\User;
use App\Models\User\Role;

class DemoUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Authors
        $this->createAuthors();
        
        // Create Contributors
        $this->createContributors();
        
        // Create Regular Users
        $this->createRegularUsers();
    }

    /**
     * Create Author users.
     */
    private function createAuthors(): void
    {
        $authors = [
            [
                'name' => 'John Smith',
                'username' => 'johnsmith',
                'email' => 'john.smith@hackathon.test',
                'first_name' => 'John',
                'last_name' => 'Smith',
                'bio' => 'Passionate writer and content creator with 5 years of experience in digital marketing.',
                'website' => 'https://johnsmith.blog',
            ],
            [
                'name' => 'Sarah Johnson',
                'username' => 'sarahj',
                'email' => 'sarah.johnson@hackathon.test',
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'bio' => 'Technology enthusiast and blogger focusing on emerging trends and innovations.',
                'linkedin' => 'https://linkedin.com/in/sarahjohnson',
            ],
            [
                'name' => 'Michael Chen',
                'username' => 'michaelc',
                'email' => 'michael.chen@hackathon.test',
                'first_name' => 'Michael',
                'last_name' => 'Chen',
                'bio' => 'Software developer turned technical writer, sharing insights about programming and development.',
                'website' => 'https://devblog.michaelchen.dev',
            ],
        ];

        $authorRole = Role::where('slug', 'author')->first();
        $createdCount = 0;

        foreach ($authors as $authorData) {
            // Check if user already exists
            if (!User::where('username', $authorData['username'])->exists()) {
                $user = User::create([
                    'name' => $authorData['name'],
                    'username' => $authorData['username'],
                    'email' => $authorData['email'],
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                // Create profile
                $user->profile()->create([
                    'first_name' => $authorData['first_name'],
                    'last_name' => $authorData['last_name'],
                    'bio' => $authorData['bio'],
                    'website' => $authorData['website'] ?? null,
                    'linkedin' => $authorData['linkedin'] ?? null,
                    'timezone' => 'UTC',
                    'language' => 'en',
                    'preferences' => [
                        'theme' => collect(['light', 'dark'])->random(),
                        'notifications' => [
                            'email_notifications' => true,
                            'content_updates' => true,
                            'comment_notifications' => true,
                        ],
                    ],
                ]);

                // Assign Author role
                if ($authorRole) {
                    $user->assignRole($authorRole);
                }
                
                $createdCount++;
            }
        }

        $this->command->info('✓ Created ' . $createdCount . ' Author users');
    }

    /**
     * Create Contributor users.
     */
    private function createContributors(): void
    {
        $contributors = [
            [
                'name' => 'Emma Wilson',
                'username' => 'emmaw',
                'email' => 'emma.wilson@hackathon.test',
                'first_name' => 'Emma',
                'last_name' => 'Wilson',
                'bio' => 'Freelance writer specializing in lifestyle and wellness content.',
            ],
            [
                'name' => 'David Rodriguez',
                'username' => 'davidr',
                'email' => 'david.rodriguez@hackathon.test',
                'first_name' => 'David',
                'last_name' => 'Rodriguez',
                'bio' => 'Marketing professional with a passion for creative writing.',
            ],
            [
                'name' => 'Lisa Thompson',
                'username' => 'lisat',
                'email' => 'lisa.thompson@hackathon.test',
                'first_name' => 'Lisa',
                'last_name' => 'Thompson',
                'bio' => 'Part-time contributor interested in environmental and sustainability topics.',
            ],
            [
                'name' => 'James Park',
                'username' => 'jamesp',
                'email' => 'james.park@hackathon.test',
                'first_name' => 'James',
                'last_name' => 'Park',
                'bio' => 'Student journalist contributing articles about education and career development.',
            ],
        ];

        $contributorRole = Role::where('slug', 'contributor')->first();
        $createdCount = 0;

        foreach ($contributors as $contributorData) {
            // Check if user already exists
            if (!User::where('username', $contributorData['username'])->exists()) {
                $user = User::create([
                    'name' => $contributorData['name'],
                    'username' => $contributorData['username'],
                    'email' => $contributorData['email'],
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                // Create profile
                $user->profile()->create([
                    'first_name' => $contributorData['first_name'],
                    'last_name' => $contributorData['last_name'],
                    'bio' => $contributorData['bio'],
                    'timezone' => 'UTC',
                    'language' => 'en',
                    'preferences' => [
                        'theme' => 'light',
                        'notifications' => [
                            'email_notifications' => true,
                            'content_updates' => false,
                            'comment_notifications' => true,
                        ],
                    ],
                ]);

                // Assign Contributor role
                if ($contributorRole) {
                    $user->assignRole($contributorRole);
                }
                
                $createdCount++;
            }
        }

        $this->command->info('✓ Created ' . $createdCount . ' Contributor users');
    }

    /**
     * Create regular users.
     */
    private function createRegularUsers(): void
    {
        $users = [
            [
                'name' => 'Alice Cooper',
                'username' => 'alicec',
                'email' => 'alice.cooper@hackathon.test',
                'first_name' => 'Alice',
                'last_name' => 'Cooper',
                'bio' => 'Regular user who enjoys reading and commenting on various topics.',
            ],
            [
                'name' => 'Bob Miller',
                'username' => 'bobm',
                'email' => 'bob.miller@hackathon.test',
                'first_name' => 'Bob',
                'last_name' => 'Miller',
                'bio' => 'Regular user interested in technology and science.',
            ],
        ];

        foreach ($users as $userData) {
            // Check if user already exists
            if (!User::where('username', $userData['username'])->exists()) {
                $user = User::create([
                    'name' => $userData['name'],
                    'username' => $userData['username'],
                    'email' => $userData['email'],
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                // Create profile
                $user->profile()->create([
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'bio' => $userData['bio'],
                    'timezone' => 'UTC',
                    'language' => 'en',
                    'preferences' => [
                        'theme' => 'light',
                        'notifications' => [
                            'email_notifications' => true,
                            'content_updates' => false,
                            'comment_notifications' => true,
                        ],
                    ],
                ]);
            }
        }

        $this->command->info('✓ Created regular users');
    }
}