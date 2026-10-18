<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand-only (owner approval 2026-10-16, wave 6.5): named filter views of
 * the admin lists, private to the user who saved them. `list` is the route
 * name of the list (e.g. `admin.articles.index`), `query` its parameters.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('saved_list_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('list', 100);
            $table->string('name', 60);
            $table->json('query');
            $table->timestamps();

            $table->unique(['user_id', 'list', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_list_views');
    }
};
