<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Amenity;

class AmenitySeeder extends Seeder
{
    public function run()
    {
       $amenities = [
        'Wi-Fi',
        'Air Conditioning',
        'Water Heater',
        'Furnished',
        'Dishwasher',
        'Parking',
        'Gym',
        'Swimming Pool',
        'Generator',
        'Elevator',
        'Pet-Friendly',
        'Security',
        'Balcony',
        'Concierge',
    ];

        foreach ($amenities as $name) {
            Amenity::create(['name' => $name]);
        }
    }
}
