<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {

            $table->date('start_date')->nullable()->after('notes');
            $table->date('end_date')->nullable()->after('start_date');

            $table->time('start_time')->nullable()->after('end_date');
            $table->time('end_time')->nullable()->after('start_time');

            $table->dateTime('paid_at')->nullable()->change();

            $table->string('currency', 3)->default('USD')->after('price');

            $table->index(['property_id', 'unit_id', 'type', 'status']);
            $table->index(['property_id', 'unit_id', 'start_date', 'end_date']);
            
             if (Schema::hasColumn('transactions', 'unit_id')) {
                $table->dropConstrainedForeignId('unit_id');
            }

            if (Schema::hasColumn('transactions', 'starting_time')) {
                $table->dropColumn('starting_time');
            }

            if (Schema::hasColumn('transactions', 'finishing_time')) {
                $table->dropColumn('finishing_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['property_id', 'unit_id', 'type', 'status']);
            $table->dropIndex(['property_id', 'unit_id', 'start_date', 'end_date']);

            $table->dropConstrainedForeignId('seller_id');

            $table->dropColumn([
                'start_date', 'end_date', 'start_time', 'end_time', 'currency'
            ]);

        });
    }
};
