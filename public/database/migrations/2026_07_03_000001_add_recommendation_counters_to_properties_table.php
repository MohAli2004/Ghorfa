<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->unsignedInteger('views_count')->default(0)->after('active');
            $table->unsignedInteger('likes_count')->default(0)->after('views_count');
        });

        // Backfill likes_count from the existing property_likes pivot table.
        if (Schema::hasTable('property_likes')) {
            DB::table('properties')->update(['likes_count' => 0]);

            $likeCounts = DB::table('property_likes')
                ->select('property_id', DB::raw('COUNT(*) as total'))
                ->groupBy('property_id')
                ->get();

            foreach ($likeCounts as $row) {
                DB::table('properties')
                    ->where('id', $row->property_id)
                    ->update(['likes_count' => $row->total]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['views_count', 'likes_count']);
        });
    }
};
