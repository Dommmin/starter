<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand-only (owner approval 2026-10-12, wave 4a): several contact form
 * recipients and the sender auto-reply in the site settings, and the
 * webhook delivery state of each contact message. The single
 * `contact_recipient_email` stays as the fallback.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->json('contact_recipients')->nullable();
            $table->boolean('contact_auto_reply')->default(false);
        });

        Schema::table('site_setting_translations', function (Blueprint $table) {
            $table->string('auto_reply_text', 1000)->nullable();
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('webhook_status', 16)->nullable()->index();
            $table->unsignedInteger('webhook_attempts')->default(0);
            $table->timestamp('webhook_delivered_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropIndex(['webhook_status']);
            $table->dropColumn(['webhook_status', 'webhook_attempts', 'webhook_delivered_at']);
        });

        Schema::table('site_setting_translations', function (Blueprint $table) {
            $table->dropColumn('auto_reply_text');
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['contact_recipients', 'contact_auto_reply']);
        });
    }
};
