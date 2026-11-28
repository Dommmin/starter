<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_blocks', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('locale', 12);
            $table->string('type', 40);
            $table->json('content');
            $table->timestamps();

            $table->index(['locale', 'name']);
        });

        Schema::table('page_sections', function (Blueprint $table): void {
            $table->foreignId('content_block_id')->nullable()->after('page_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('page_sections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('content_block_id');
        });

        Schema::dropIfExists('content_blocks');
    }
};
