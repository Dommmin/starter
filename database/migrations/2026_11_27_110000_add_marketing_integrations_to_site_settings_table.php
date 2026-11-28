<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('ga4_id', 32)->nullable()->after('plausible_domain');
            $table->string('gtm_id', 32)->nullable()->after('ga4_id');
            $table->string('meta_pixel_id', 32)->nullable()->after('gtm_id');
            $table->string('chat_provider', 32)->nullable()->after('meta_pixel_id');
            $table->string('chat_widget_id', 120)->nullable()->after('chat_provider');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'ga4_id',
                'gtm_id',
                'meta_pixel_id',
                'chat_provider',
                'chat_widget_id',
            ]);
        });
    }
};
