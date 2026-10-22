<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL `TIMESTAMP` ends on 2038-01-19, so an article scheduled later could
 * not be saved. `DATETIME` has no such limit; PostgreSQL and SQLite map both
 * types to the same column, so only MySQL changes. Values are stored in UTC
 * by the application either way.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('article_translations', function (Blueprint $table) {
            $table->dateTime('published_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations. On MySQL this fails while any publication date
     * lies after 2038-01-19.
     */
    public function down(): void
    {
        Schema::table('article_translations', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->change();
        });
    }
};
