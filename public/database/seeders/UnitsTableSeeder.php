<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class UnitsTableSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $rows = [
            // Base
            ['name' => 'US Dollar',        'code' => 'USD', 'symbol' => '$',  'price_in_dollar' => 1.0000],
            ['name' => 'Lebanese Pound',   'code' => 'LBP', 'symbol' => 'ل.ل', 'price_in_dollar' => 0.000011], 
            ['name' => 'Saudi Riyal',      'code' => 'SAR', 'symbol' => '﷼',  'price_in_dollar' => 0.2667],
            ['name' => 'UAE Dirham',       'code' => 'AED', 'symbol' => 'د.إ', 'price_in_dollar' => 0.2723],
            ['name' => 'Egyptian Pound',   'code' => 'EGP', 'symbol' => 'E£', 'price_in_dollar' => 0.0200],
            ['name' => 'Jordanian Dinar',  'code' => 'JOD', 'symbol' => 'JD', 'price_in_dollar' => 1.4100],
            // Nearby / common
            ['name' => 'Euro',             'code' => 'EUR', 'symbol' => '€',  'price_in_dollar' => 1.0900],
            ['name' => 'British Pound',    'code' => 'GBP', 'symbol' => '£',  'price_in_dollar' => 1.2700],
            ['name' => 'Turkish Lira',     'code' => 'TRY', 'symbol' => '₺',  'price_in_dollar' => 0.0300],
        ];

        
        $rows = array_map(function ($r) use ($now) {
            return array_merge($r, ['created_at' => $now, 'updated_at' => $now]);
        }, $rows);

        // upsert by unique code (adjust if you have a unique index on code)
        DB::table('units')->upsert(
            $rows,
            ['code'],                              // unique key
            ['name', 'symbol', 'price_in_dollar', 'updated_at'] // columns to update on conflict
        );
    }
}
