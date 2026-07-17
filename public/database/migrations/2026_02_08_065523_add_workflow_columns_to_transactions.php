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
        Schema::table('transactions', function (Blueprint $table) {
            
            $table->dateTime('buyer_approved_at')->nullable()->after('status');

            $table->boolean('rules_accepted')->default(false)->after('notes');
            $table->text('rules_exceptions')->nullable()->after('rules_accepted');

            $table->string('contract_path')->nullable()->after('rules_exceptions');
            $table->dateTime('contract_generated_at')->nullable()->after('contract_path');

            $table->dateTime('seller_payment_confirmed_at')->nullable()->after('paid_at');

            $table->dateTime('refund_requested_at')->nullable()->after('seller_payment_confirmed_at');
            $table->dateTime('refund_confirmed_by_buyer_at')->nullable()->after('refund_requested_at');

            $table->text('cancel_reason')->nullable()->after('refund_confirmed_by_buyer_at');

            $table->index(['property_id', 'type', 'status']);
            $table->index(['property_id', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            //
        });
    }
};
