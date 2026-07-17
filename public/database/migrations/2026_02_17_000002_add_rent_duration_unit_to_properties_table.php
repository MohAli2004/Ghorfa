<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Fixed rule: which rental duration units the landlord accepts.
            // Stored as SET (comma-separated string), e.g. "day,week".
            $table->set('rent_duration_units', ['day', 'week', 'month', 'year'])->nullable()
                ->after('price_duration');
        });

        DB::table('properties')
            ->whereNull('rent_duration_units')
            ->update(['rent_duration_units' => 'day,week,month,year']);
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('rent_duration_units');
        });
    }
};

