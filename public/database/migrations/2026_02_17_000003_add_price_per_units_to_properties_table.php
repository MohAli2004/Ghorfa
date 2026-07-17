<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('price_per_day', 12, 2)->nullable()->after('price');
            $table->decimal('price_per_week', 12, 2)->nullable()->after('price_per_day');
            $table->decimal('price_per_month', 12, 2)->nullable()->after('price_per_week');
            $table->decimal('price_per_year', 12, 2)->nullable()->after('price_per_month');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['price_per_day', 'price_per_week', 'price_per_month', 'price_per_year']);
        });
    }
};

