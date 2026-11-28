<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->string('layout_mode', 20)->default('rich_text')->after('updated_by');
        });

        Schema::create('page_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('type', 40);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('enabled')->default(true);
            $table->json('content');
            $table->timestamps();

            $table->index(['page_id', 'locale', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');

        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn('layout_mode');
        });
    }
};
