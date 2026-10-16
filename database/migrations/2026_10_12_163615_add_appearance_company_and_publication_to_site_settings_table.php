<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand-only: appearance (accent, typography, default theme, favicon),
 * company details (tax id, map link, opening hours, Search Console token)
 * and publication switches (coming soon, site-wide noindex). Every column is
 * nullable or has a default, so code without these settings keeps working.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('accent', 20)->nullable();
            $table->string('typography', 20)->nullable();
            $table->string('default_theme', 10)->default('system');
            $table->foreignId('favicon_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('tax_id', 20)->nullable();
            $table->string('map_url', 500)->nullable();
            $table->json('opening_hours')->nullable();
            $table->string('google_site_verification', 100)->nullable();
            $table->boolean('coming_soon')->default(false);
            $table->boolean('noindex')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('favicon_media_id');
            $table->dropColumn([
                'accent',
                'typography',
                'default_theme',
                'tax_id',
                'map_url',
                'opening_hours',
                'google_site_verification',
                'coming_soon',
                'noindex',
            ]);
        });
    }
};
