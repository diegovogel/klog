<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('web_clippings', function (Blueprint $table) {
            $table->string('title', 512)->nullable()->after('url');
            $table->text('content')->nullable()->after('title');
            $table->unsignedTinyInteger('fetch_attempts')->default(0)->after('content');
            $table->unsignedTinyInteger('screenshot_attempts')->default(0)->after('fetch_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_clippings', function (Blueprint $table) {
            $table->dropColumn(['title', 'content', 'fetch_attempts', 'screenshot_attempts']);
        });
    }
};
