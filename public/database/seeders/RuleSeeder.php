<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Rule;

class RuleSeeder extends Seeder
{
    public function run()
    {
        $rules = [
            'Allowing Smoking',
            'Pet Friendly',
            'Student Friendly',
            'No Parties',
            'Quiet Hours',
            'No Alcohol',
            'No Loud Music',
        ];

        foreach ($rules as $name) {
            Rule::create(['name' => $name]);
        }
    }
}
