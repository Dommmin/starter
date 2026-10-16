<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand-only (owner approval 2026-10-12, wave 4b): configurable contact
 * forms with extra fields (labels and options per locale as JSON) and the
 * form plus extra values of each stored message. Name, e-mail and message
 * stay the fixed columns of every message.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contact_forms', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->json('title');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('contact_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_form_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('type', 16);
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->json('label');
            $table->json('options')->nullable();
            $table->timestamps();

            $table->unique(['contact_form_id', 'key']);
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->foreignId('contact_form_id')->nullable()->constrained()->nullOnDelete();
            $table->json('fields')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_form_id');
            $table->dropColumn('fields');
        });

        Schema::dropIfExists('contact_form_fields');
        Schema::dropIfExists('contact_forms');
    }
};
