<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // e.g. "US Dollar"
            $table->string('code')->unique();    // make code unique so upsert/seed works
            $table->string('symbol');
            $table->double('price_in_dollar'); // 1 LBP in USD, or 1 unit -> USD
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
