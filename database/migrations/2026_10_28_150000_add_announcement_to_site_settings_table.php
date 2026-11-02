<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand-only: the announcement bar of the public site. The switch defaults
 * to off and every other value is nullable (the tone defaults to info), so
 * code without the bar keeps working. Texts are translated per public
 * locale.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->boolean('announcement_enabled')->default(false);
            $table->string('announcement_tone', 16)->default('info');
            $table->string('announcement_link_url', 2048)->nullable();
            $table->timestamp('announcement_starts_at')->nullable();
            $table->timestamp('announcement_ends_at')->nullable();
        });

        Schema::table('site_setting_translations', function (Blueprint $table) {
            $table->string('announcement_text', 160)->nullable();
            $table->string('announcement_link_label', 60)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_setting_translations', function (Blueprint $table) {
            $table->dropColumn(['announcement_text', 'announcement_link_label']);
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'announcement_enabled',
                'announcement_tone',
                'announcement_link_url',
                'announcement_starts_at',
                'announcement_ends_at',
            ]);
        });
    }
};
