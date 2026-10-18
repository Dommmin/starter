<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand-only (owner approval 2026-10-16, wave 6.3): a trash for pages and
 * articles. A deleted record keeps its translations and slugs until an
 * administrator empties it or `content:purge-trash` removes it after 30
 * days (then the FK cascade deletes the translations).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->softDeletes()->index();
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->softDeletes()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropSoftDeletes();
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropSoftDeletes();
        });
    }
};
