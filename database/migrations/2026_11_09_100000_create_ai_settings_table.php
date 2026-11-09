<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand-only: the AI provider and its API key set from the panel (owner
 * approval 2026-11-09). Singleton row; without it the AI_PROVIDER and
 * provider key environment values apply, so code without it keeps working.
 * The key is stored encrypted with APP_KEY and kept apart from
 * site_settings, which are cached and shared with the frontend.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('provider', 32)->nullable();
            $table->text('api_key')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
