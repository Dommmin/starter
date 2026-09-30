<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. One section of each type per public locale; the
     * panel only edits, toggles and reorders them (no create/delete).
     */
    public function up(): void
    {
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 12);
            $table->string('type');
            $table->unsignedSmallInteger('position');
            $table->boolean('enabled')->default(false);
            $table->json('content');
            $table->timestamps();

            $table->unique(['locale', 'type']);
            $table->index(['locale', 'enabled', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_sections');
    }
};
