<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->restrictOnDelete();
        });

        // Backfill existing memories to the first user (oldest by id).
        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId !== null) {
            DB::table('memories')
                ->whereNull('user_id')
                ->update(['user_id' => $firstUserId]);
        }

        // Enforce non-null now that all existing rows have been backfilled.
        // If rows are still null here, either there were memories without users
        // (impossible in normal use) or no users exist — in both cases, refuse
        // to proceed rather than corrupt the schema.
        $orphanCount = DB::table('memories')->whereNull('user_id')->count();
        if ($orphanCount > 0) {
            throw new RuntimeException(
                "Cannot enforce user_id non-null: {$orphanCount} memories have no user. "
                .'Create a user, assign those memories, then re-run this migration.'
            );
        }

        Schema::table('memories', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
