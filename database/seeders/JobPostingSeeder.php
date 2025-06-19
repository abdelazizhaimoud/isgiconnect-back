<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User\User;
use App\Models\JobPosting;

class JobPostingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = User::where('role', 'company')->get();

        if ($companies->isEmpty()) {
            $this->command->error('No company users found. Please run CompanySeeder first.');
            return;
        }

        $jobPostings = [
            [
                'title' => 'Software Engineer',
                'description' => 'Develop and maintain web applications.',
                'requirements' => 'PHP, Laravel, Vue.js',
                'location' => 'Remote',
                'type' => 'full-time',
                'application_deadline' => now()->addDays(30)->toDateString(),
            ],
            [
                'title' => 'Frontend Developer',
                'description' => 'Create amazing user interfaces.',
                'requirements' => 'React, JavaScript, HTML, CSS',
                'location' => 'New York, NY',
                'type' => 'full-time',
                'application_deadline' => now()->addDays(20)->toDateString(),
            ],
        ];

        foreach ($jobPostings as $postingData) {
            JobPosting::create(array_merge($postingData, [
                'company_id' => $companies->random()->id,
            ]));
        }

        $this->command->info('✓ Created ' . count($jobPostings) . ' Job Postings');
    }
}
