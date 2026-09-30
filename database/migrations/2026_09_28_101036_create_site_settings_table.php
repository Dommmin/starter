<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Singleton table: the primary key is not auto-incrementing and the
     * application only ever writes the row with id = 1
     * (SiteSetting::SINGLETON_ID), so a second row cannot appear through the
     * application on any supported database.
     */
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('site_name', 120);
            $table->foreignId('logo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('og_image_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('contact_email', 254)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('address_line', 200)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('contact_recipient_email', 254)->nullable();
            $table->json('social_links')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
