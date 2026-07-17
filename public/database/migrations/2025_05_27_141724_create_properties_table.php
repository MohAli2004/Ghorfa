<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('property_type');
            $table->string('listing_type');
            $table->string('country');
            $table->string('city');
            $table->string('address');
            $table->decimal('price', 12, 2);
            $table->decimal('area_m3', 10, 2)->nullable();    // or rename to area_m2 if you mean m²
            $table->unsignedInteger('room_nb')->nullable();
            $table->unsignedInteger('bathroom_nb')->nullable();
            $table->unsignedInteger('bedroom_nb')->nullable();
            $table->decimal('latitude', 10, 8)->nullable(); // Coordinates - nullable for backward compatibility but required in validation
            $table->decimal('longitude', 11, 8)->nullable(); // Coordinates - nullable for backward compatibility but required in validation
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
