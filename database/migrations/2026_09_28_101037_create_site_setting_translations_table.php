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
        Schema::create('site_setting_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('site_setting_id');
            $table->foreign('site_setting_id')->references('id')->on('site_settings')->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('tagline', 200)->nullable();
            $table->string('footer_text', 500)->nullable();
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamps();

            $table->unique(['site_setting_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_setting_translations');
    }
};
