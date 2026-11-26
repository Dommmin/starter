<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand-only (owner approval 2026-11-05, AI assistant stage 6): the AI
 * category of a contact message (App\Enums\ContactMessageCategory, null
 * until classified) and the moment it was classified. No summary column.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('category', 16)->nullable();
            $table->timestamp('classified_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropColumn(['category', 'classified_at']);
        });
    }
};
