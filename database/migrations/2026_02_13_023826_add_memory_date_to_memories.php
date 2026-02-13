<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->dropIndex(['captured_at']);
            $table->dropColumn('captured_at');
            $table->date('memory_date')->nullable()->after('content')->index();
        });
    }

    public function down(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->dropIndex(['memory_date']);
            $table->dropColumn('memory_date');
            $table->timestamp('captured_at')->nullable()->after('content')->index();
        });
    }
};
