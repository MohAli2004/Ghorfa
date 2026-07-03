<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional AI upgrade: stores pre-generated OpenAI embeddings per property.
     * Vectors are generated on create/update — never on every page load.
     */
    public function up(): void
    {
        Schema::create('property_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('model', 64)->default('text-embedding-3-small');
            $table->json('embedding');
            $table->text('source_text')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_embeddings');
    }
};
