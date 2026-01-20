<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memory_id')->constrained()->cascadeOnDelete();

            // File information
            $table->string('filename');
            $table->string('original_filename');
            $table->string('mime_type'); // MimeType enum.
            $table->unsignedBigInteger('size');
            $table->string('disk')->default('local');
            $table->string('path')->unique();

            $table->string('type'); // MediaType enum.
            $table->json('metadata')->nullable(); // Width, height, duration, etc.
            $table->unsignedInteger('order')->default(0); // For memories with multiple media.

            $table->timestamps();
            $table->softDeletes();

            $table->index(['memory_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
