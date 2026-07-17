<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('property_id')->constrained('properties');
            $table->enum('type', ['buy', 'rent', 'refund']);
            $table->decimal('price', 10, 2);
            $table->foreignId('unit_id')->nullable()->constrained('units')->cascadeOnDelete(); 
            $table->enum('status', ['pending', 'paid','confirmed', 'cancelled_by_buyer', 'cancelled_by_seller', 'completed', 'refunded'])->default('pending');   
            $table->text('notes')->nullable();
            $table->time('starting_time')->nullable();
            $table->time('finishing_time')->nullable();
            $table->time('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
