<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Property;
use App\Models\Rule;

class RulePropertySeeder extends Seeder
{
    public function run()
    {
        $rules = Rule::all();
        $ruleIds = $rules->pluck('id')->toArray();
        foreach (Property::all() as $property) {
            $property->rules()->sync($ruleIds);
        }
    }
}
