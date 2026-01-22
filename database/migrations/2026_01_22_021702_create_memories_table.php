<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->string('type')->nullable(); // MemoryType enum.
            $table->timestamp('captured_at')->nullable(); // When the memory happened. Only for media.
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('captured_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
