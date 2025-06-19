<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        $studentsNames = [
            ['Youssef', 'El Amrani', 'Male'],
            ['Aya', 'Benkirane', 'Female'],
            ['Omar', 'El Fassi', 'Male'],
            ['Sara', 'Bouazza', 'Female'],
            ['Hamza', 'El Hajji', 'Male'],
            ['Nada', 'Zahidi', 'Female'],
            ['Anas', 'Bennis', 'Male'],
            ['Salma', 'Bouhadi', 'Female'],
            ['Amine', 'Bakkali', 'Male'],
            ['Rania', 'Laaroussi', 'Female'],
            ['Tariq', 'Hamidi', 'Male'],
            ['Imane', 'El Idrissi', 'Female'],
            ['Nabil', 'Tazi', 'Male'],
            ['Lina', 'Moutaouakil', 'Female'],
            ['Sami', 'El Ghaoui', 'Male'],
            ['Noor', 'Khattabi', 'Female'],
            ['Adnan', 'Chafik', 'Male'],
            ['Sara', 'El Mehdi', 'Female'],
            ['Ismail', 'Mernissi', 'Male'],
            ['Meryem', 'Boushaba', 'Female'],
            ['Khalid', 'Jabri', 'Male'],
            ['Rim', 'Alaoui', 'Female'],
            ['Iyad', 'Rahmouni', 'Male'],
            ['Leila', 'Amrani', 'Female'],
            ['Rayan', 'Bouhlal', 'Male'],
            ['Nadia', 'El Khatib', 'Female'],
            ['Adam', 'Bennis', 'Male'],
            ['Yasmine', 'Mellouk', 'Female'],
            ['Younes', 'Ouazzani', 'Male'],
            ['Hiba', 'Sebti', 'Female'],
            ['Bilal', 'Tahiri', 'Male'],
            ['Siham', 'El Yousfi', 'Female'],
            ['Othman', 'Zerouali', 'Male'],
            ['Nisrine', 'Belkadi', 'Female'],
            ['Yassine', 'Moujahid', 'Male'],
            ['Salma', 'Derdabi', 'Female'],
            ['Ali', 'Fahmi', 'Male'],
            ['Mona', 'Benchekroun', 'Female'],
            ['Karim', 'El Idrissi', 'Male'],
            ['Hajar', 'Ghallab', 'Female'],
        ];
        for ($i = 1; $i <= 31; $i++) {
            $studentsName = $studentsNames[$i - 1];
            $user = User::create([
                'displayname' =>  "$studentsName[0] $studentsName[1]",
                'username' => "$studentsName[0].$studentsName[1]@gmail.com",
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'profilepicture' => 'images/default.png',
                'active' => true
            ]);
        }
    }
}
