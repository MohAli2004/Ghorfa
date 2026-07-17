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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'property_created', 'property_updated', 'property_deleted', 'property_approved', 'property_rejected', 'user_registered', 'application_approved', 'application_rejected', etc.
            $table->string('description');
            $table->morphs('subject'); // subject_type, subject_id (polymorphic relation)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Who performed the action
            $table->json('properties')->nullable(); // Additional data about the activity
            $table->timestamps();
            
            $table->index('type');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
