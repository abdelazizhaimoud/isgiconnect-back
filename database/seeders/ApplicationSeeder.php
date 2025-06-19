<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User\User;
use App\Models\JobPosting;
use App\Models\Application;

class ApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = User::where('role', 'student')->get();

        $jobPostings = JobPosting::all();

        if ($students->isEmpty() || $jobPostings->isEmpty()) {
            $this->command->error('No students or job postings found. Please run StudentSeeder and JobPostingSeeder first.');
            return;
        }

        // Create one application for each student
        foreach ($students as $student) {
            Application::create([
                'student_id' => $student->id,
                'job_posting_id' => $jobPostings->random()->id,
                'status' => 'pending',
            ]);
        }

        $this->command->info('✓ Created ' . $students->count() . ' Applications');
    }
}
