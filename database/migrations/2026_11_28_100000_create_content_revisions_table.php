<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_revisions', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->timestamp('created_at');

            $table->index(['subject_type', 'subject_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_revisions');
    }
};
