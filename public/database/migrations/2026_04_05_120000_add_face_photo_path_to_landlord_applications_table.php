<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landlord_applications', function (Blueprint $table) {
            $table->string('face_photo_path')->nullable()->after('document_back_path');
        });
    }

    public function down(): void
    {
        Schema::table('landlord_applications', function (Blueprint $table) {
            $table->dropColumn('face_photo_path');
        });
    }
};
