<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UnitsTableSeeder::class,
            UserSeeder::class,
            PropertySeeder::class,
            AmenitySeeder::class,
            AmenityPropertySeeder::class,  
            RuleSeeder::class,
            RulePropertySeeder::class, 
        ]);
    }
} 