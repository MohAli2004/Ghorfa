<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // use firstOrCreate so running the seeder multiple times won't violate unique constraints
        User::firstOrCreate([
            'email' => 'admin@example.com',
        ], [
            'first_name' => 'Admin',
            'last_name' => 'User',
            'password' => Hash::make('123'),
            'role' => 'admin',
            'phone_nb' => '70000001',
            'profile_image' => null,
            'date_of_birth' => '1990-01-01',
            'address' => 'Beirut, Lebanon'
        ]);

        User::firstOrCreate([
            'email' => 'user@example.com',
        ], [
            'first_name' => 'Regular',
            'last_name' => 'User',
            'password' => Hash::make('password'),
            'role' => 'client',
            'phone_nb' => '70000002',
            'profile_image' => null,
            'date_of_birth' => '1995-05-15',
            'address' => 'Tripoli, Lebanon'
        ]);
    }
} 