<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User\User;
use Illuminate\Support\Facades\Hash;

class CreateCompanyUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-company-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user with the company role for testing.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->ask("Enter company user's name");
        $username = $this->ask("Enter company user's username");
        $email = $this->ask("Enter company user's email");
        $password = $this->secret("Enter company user's password");

        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'company',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->info('Company user created successfully!');
        $this->info('Name: ' . $user->name);
        $this->info('Username: ' . $user->username);
        $this->info('Email: ' . $user->email);
        $this->info('Role: ' . $user->role);
    }
}
