<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser    = User::where('email', 'admin@example.com')->first();
        $regularUser  = User::where('email', 'user@example.com')->first();
        
        // Get USD unit (default unit_id = 1)
        $usdUnit = Unit::where('code', 'USD')->first();
        $defaultUnitId = $usdUnit ? $usdUnit->id : 1;

        // Beirut coordinates: 33.8949, 35.5031
        $beirutProperties = [
            [
                'title'        => 'Modern Apartment in Downtown Beirut',
                'description'  => 'Beautiful modern apartment in the heart of Beirut with sea view.',
                'property_type'=> 'apartment',
                'listing_type' => 'rent',
                'country'      => 'Lebanon',
                'city'         => 'Beirut',
                'address'      => 'Downtown Beirut',
                'price'        => 1500,
                'unit_id'      => $defaultUnitId,
                'area_m3'      => 120,
                'room_nb'      => 3,
                'bathroom_nb'  => 2,
                'bedroom_nb'   => 2,
                'latitude'     => 33.8949,
                'longitude'    => 35.5031,
                'user_id'      => $adminUser?->id,
            ],
            [
                'title'        => 'Luxury House in Achrafieh',
                'description'  => 'Spacious house with garden in one of Beirut\'s most prestigious neighborhoods.',
                'property_type'=> 'house',
                'listing_type' => 'sale',
                'country'      => 'Lebanon',
                'city'         => 'Beirut',
                'address'      => 'Achrafieh',
                'price'        => 850000,
                'unit_id'      => $defaultUnitId,
                'area_m3'      => 300,
                'room_nb'      => 6,
                'bathroom_nb'  => 4,
                'bedroom_nb'   => 4,
                'latitude'     => 33.8965,
                'longitude'    => 35.5123,
                'user_id'      => $regularUser?->id,
            ],
            [
                'title'        => 'Cozy Studio in Hamra',
                'description'  => 'Perfect studio apartment for students or young professionals.',
                'property_type'=> 'apartment',
                'listing_type' => 'rent',
                'country'      => 'Lebanon',
                'city'         => 'Beirut',
                'address'      => 'Hamra Street',
                'price'        => 800,
                'unit_id'      => $defaultUnitId,
                'area_m3'      => 45,
                'room_nb'      => 1,
                'bathroom_nb'  => 1,
                'bedroom_nb'   => 1,
                'latitude'     => 33.8968,
                'longitude'    => 35.4821,
                'user_id'      => $regularUser?->id,
            ],
        ];

        // Baalbek coordinates: 34.0058, 36.2181
        $baalbekProperties = [
            [
                'title'        => 'Traditional House in Baalbek',
                'description'  => 'Authentic Lebanese house with traditional architecture.',
                'property_type'=> 'house',
                'listing_type' => 'sale',
                'country'      => 'Lebanon',
                'city'         => 'Baalbek',
                'address'      => 'Old City',
                'price'        => 250000,
                'unit_id'      => $defaultUnitId,
                'area_m3'      => 200,
                'room_nb'      => 5,
                'bathroom_nb'  => 3,
                'bedroom_nb'   => 3,
                'latitude'     => 34.0058,
                'longitude'    => 36.2181,
                'user_id'      => $regularUser?->id,
            ],
            [
                'title'        => 'Farm House in Baalbek',
                'description'  => 'Beautiful farm house with large garden and agricultural land.',
                'property_type'=> 'house',
                'listing_type' => 'sale',
                'country'      => 'Lebanon',
                'city'         => 'Baalbek',
                'address'      => 'Ras Baalbek',
                'price'        => 350000,
                'unit_id'      => $defaultUnitId,
                'area_m3'      => 400,
                'room_nb'      => 7,
                'bathroom_nb'  => 4,
                'bedroom_nb'   => 5,
                'latitude'     => 34.0123,
                'longitude'    => 36.2245,
                'user_id'      => $regularUser?->id,
            ],
            [
                'title'        => 'Modern Apartment in Baalbek',
                'description'  => 'Newly built apartment with modern amenities.',
                'property_type'=> 'apartment',
                'listing_type' => 'rent',
                'country'      => 'Lebanon',
                'city'         => 'Baalbek',
                'address'      => 'New City',
                'price'        => 500,
                'unit_id'      => $defaultUnitId,
                'area_m3'      => 100,
                'room_nb'      => 3,
                'bathroom_nb'  => 2,
                'bedroom_nb'   => 2,
                'latitude'     => 34.0089,
                'longitude'    => 36.2156,
                'user_id'      => $regularUser?->id,
            ],
            [
                'title'        => 'House with Mountain View',
                'description'  => 'Luxurious house with stunning mountain views.',
                'property_type'=> 'house',
                'listing_type' => 'sale',
                'country'      => 'Lebanon',
                'city'         => 'Baalbek',
                'address'      => 'Mountain Area',
                'price'        => 450000,
                'unit_id'      => $defaultUnitId,
                'area_m3'      => 350,
                'room_nb'      => 6,
                'bathroom_nb'  => 4,
                'bedroom_nb'   => 4,
                'latitude'     => 34.0187,
                'longitude'    => 36.2289,
                'user_id'      => $regularUser?->id,
            ],
        ];

        // Tyre coordinates: 33.2711, 35.1964
        $tyreProperties = [
            [
                'title'        => 'Beachfront Apartment in Tyre',
                'description'  => 'Stunning apartment with direct access to the beach.',
                'property_type'=> 'apartment',
                'listing_type' => 'rent',
                'country'      => 'Lebanon',
                'city'         => 'Tyre',
                'address'      => 'Beach Road',
                'price'        => 1200,
                'unit_id'      => $defaultUnitId,
                'area_m3'      => 150,
                'room_nb'      => 4,
                'bathroom_nb'  => 2,
                'bedroom_nb'   => 3,
                'latitude'     => 33.2711,
                'longitude'    => 35.1964,
                'user_id'      => $regularUser?->id ?? 2,
            ],
            [
                'title'        => 'Traditional House in Old Tyre',
                'description'  => 'Charming house in the historic part of Tyre.',
                'property_type'=> 'house',
                'listing_type' => 'sale',
                'country'      => 'Lebanon',
                'city'         => 'Tyre',
                'address'      => 'Old City',
                'price'        => 300000,
                'unit_id'      => $defaultUnitId,
                'area_m3'      => 250,
                'room_nb'      => 5,
                'bathroom_nb'  => 3,
                'bedroom_nb'   => 3,
                'latitude'     => 33.2689,
                'longitude'    => 35.2012,
                'user_id'      => $adminUser?->id ?? 1,
            ],
        ];

        // Saida coordinates: 33.5631, 35.3689
        $saidaProperty = [
            [
                'title'        => 'Luxury Apartment in Saida',
                'description'  => 'High-end apartment with sea view in Saida.',
                'property_type'=> 'apartment',
                'listing_type' => 'sale',
                'country'      => 'Lebanon',
                'city'         => 'Saida',
                'address'      => 'Seafront',
                'price'        => 280000,
                'unit_id'      => $defaultUnitId,
                'area_m3'      => 180,
                'room_nb'      => 4,
                'bathroom_nb'  => 3,
                'bedroom_nb'   => 3,
                'latitude'     => 33.5631,
                'longitude'    => 35.3689,
                'user_id'      => $regularUser?->id ?? 2,
            ],
        ];

        $allProperties = array_merge(
            $beirutProperties,
            $baalbekProperties,
            $tyreProperties,
            $saidaProperty
        );

        foreach ($allProperties as $propertyData) {
            Property::create($propertyData);
        }
    }
}
