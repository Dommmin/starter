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
        Schema::create('page_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 12);
            $table->string('old_slug', 200);
            $table->foreignId('page_translation_id')->constrained('page_translations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['locale', 'old_slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_slug_redirects');
    }
};
