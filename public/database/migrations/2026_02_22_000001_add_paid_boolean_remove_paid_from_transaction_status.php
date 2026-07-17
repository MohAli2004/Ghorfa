<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove 'paid' from status enum and add a boolean `paid` column.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('paid')->default(false)->after('status');
        });

        // Migrate existing status='paid' to paid=true and status='confirmed'
        DB::table('transactions')->where('status', 'paid')->update([
            'paid' => true,
            'status' => 'confirmed',
        ]);

        // Remove 'paid' from the status enum (MySQL)
        DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending', 'confirmed', 'cancelled_by_buyer', 'cancelled_by_seller', 'completed', 'refunded') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore enum to include 'paid'
        DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending', 'paid', 'confirmed', 'cancelled_by_buyer', 'cancelled_by_seller', 'completed', 'refunded') NOT NULL DEFAULT 'pending'");

        // Set status = 'paid' where paid = true
        DB::table('transactions')->where('paid', true)->update(['status' => 'paid']);

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('paid');
        });
    }
};
