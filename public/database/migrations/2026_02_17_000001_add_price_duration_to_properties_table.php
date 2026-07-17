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
            $table->enum('price_duration', ['day', 'week', 'month', 'year'])->default('month')->after('price');
        });

        // Backfill existing rows for consistency.
        DB::table('properties')
            ->whereNull('price_duration')
            ->update(['price_duration' => 'month']);
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('price_duration');
        });
    }
};

