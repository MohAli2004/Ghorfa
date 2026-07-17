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
            if (! Schema::hasColumn('transactions', 'buyer_approved_at')) {
                $table->dateTime('buyer_approved_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('transactions', 'rules_accepted')) {
                $table->boolean('rules_accepted')->default(false)->after('notes');
            }

            if (! Schema::hasColumn('transactions', 'rules_exceptions')) {
                $table->text('rules_exceptions')->nullable()->after('rules_accepted');
            }

            if (! Schema::hasColumn('transactions', 'contract_path')) {
                $table->string('contract_path')->nullable()->after('rules_exceptions');
            }

            if (! Schema::hasColumn('transactions', 'contract_generated_at')) {
                $table->dateTime('contract_generated_at')->nullable()->after('contract_path');
            }

            if (! Schema::hasColumn('transactions', 'seller_payment_confirmed_at')) {
                $table->dateTime('seller_payment_confirmed_at')->nullable()->after('paid_at');
            }

            if (! Schema::hasColumn('transactions', 'refund_requested_at')) {
                $table->dateTime('refund_requested_at')->nullable()->after('seller_payment_confirmed_at');
            }

            if (! Schema::hasColumn('transactions', 'refund_confirmed_by_buyer_at')) {
                $table->dateTime('refund_confirmed_by_buyer_at')->nullable()->after('refund_requested_at');
            }

            if (! Schema::hasColumn('transactions', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('refund_confirmed_by_buyer_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'buyer_approved_at')) {
                $table->dropColumn('buyer_approved_at');
            }

            if (Schema::hasColumn('transactions', 'rules_accepted')) {
                $table->dropColumn('rules_accepted');
            }

            if (Schema::hasColumn('transactions', 'rules_exceptions')) {
                $table->dropColumn('rules_exceptions');
            }

            if (Schema::hasColumn('transactions', 'contract_path')) {
                $table->dropColumn('contract_path');
            }

            if (Schema::hasColumn('transactions', 'contract_generated_at')) {
                $table->dropColumn('contract_generated_at');
            }

            if (Schema::hasColumn('transactions', 'seller_payment_confirmed_at')) {
                $table->dropColumn('seller_payment_confirmed_at');
            }

            if (Schema::hasColumn('transactions', 'refund_requested_at')) {
                $table->dropColumn('refund_requested_at');
            }

            if (Schema::hasColumn('transactions', 'refund_confirmed_by_buyer_at')) {
                $table->dropColumn('refund_confirmed_by_buyer_at');
            }

            if (Schema::hasColumn('transactions', 'cancel_reason')) {
                $table->dropColumn('cancel_reason');
            }
        });
    }
};
