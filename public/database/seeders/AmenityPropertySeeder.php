<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Property;
use App\Models\Amenity;

class AmenityPropertySeeder extends Seeder
{
    public function run()
    {
        $amenities = Amenity::all();
        $amenityIds  = $amenities->pluck('id')->toArray();
        foreach (Property::all() as $property) {
            $property->amenities()->sync($amenityIds);
        }
    }
}
