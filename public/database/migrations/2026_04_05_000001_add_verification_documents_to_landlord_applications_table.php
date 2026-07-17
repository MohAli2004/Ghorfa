<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landlord_applications', function (Blueprint $table) {
            $table->string('document_type', 32)->nullable()->after('trade_license');
            $table->string('document_front_path')->nullable()->after('document_type');
            $table->string('document_back_path')->nullable()->after('document_front_path');
        });
    }

    public function down(): void
    {
        Schema::table('landlord_applications', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'document_front_path', 'document_back_path']);
        });
    }
};
