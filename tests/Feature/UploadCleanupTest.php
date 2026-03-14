<?php

use App\Models\UploadSession;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

describe('upload cleanup', function () {
    it('deletes incomplete sessions older than the configured TTL', function () {
        Storage::fake('local');
        $user = User::factory()->create();

        // Create the session 25 hours ago
        $this->travel(-25)->hours();
        $old = UploadSession::create([
            'user_id' => $user->id,
            'original_filename' => 'old.mp4',
            'mime_type' => 'video/mp4',
            'total_size' => 5000000,
            'total_chunks' => 3,
            'received_chunks' => 1,
            'received_chunk_indices' => [0],
        ]);
        $this->travelBack();

        Storage::disk('local')->makeDirectory($old->chunksDirectory());
        Storage::disk('local')->put($old->chunksDirectory().'0.part', 'data');

        // Run the cleanup logic
        $ttlHours = config('klog.uploads.session_ttl', 24);

        UploadSession::where('created_at', '<', now()->subHours($ttlHours))
            ->whereNull('completed_at')
            ->each(function (UploadSession $session) {
                Storage::disk('local')->deleteDirectory($session->chunksDirectory());
                $session->delete();
            });

        expect(UploadSession::find($old->id))->toBeNull();
        Storage::disk('local')->assertMissing($old->chunksDirectory().'0.part');
    });

    it('preserves incomplete sessions newer than the configured TTL', function () {
        $user = User::factory()->create();

        $recent = UploadSession::create([
            'user_id' => $user->id,
            'original_filename' => 'recent.mp4',
            'mime_type' => 'video/mp4',
            'total_size' => 5000000,
            'total_chunks' => 3,
            'received_chunks' => 1,
            'received_chunk_indices' => [0],
        ]);

        $ttlHours = config('klog.uploads.session_ttl', 24);

        UploadSession::where('created_at', '<', now()->subHours($ttlHours))
            ->whereNull('completed_at')
            ->each(function (UploadSession $session) {
                $session->delete();
            });

        expect(UploadSession::find($recent->id))->not->toBeNull();
    });

    it('deletes completed sessions older than 7 days', function () {
        $user = User::factory()->create();

        $old = UploadSession::create([
            'user_id' => $user->id,
            'original_filename' => 'done.jpg',
            'mime_type' => 'image/jpeg',
            'total_size' => 5000,
            'total_chunks' => 1,
            'received_chunks' => 1,
            'received_chunk_indices' => [0],
            'completed_at' => now()->subDays(8),
            'path' => 'uploads/2026/03/test.jpg',
        ]);

        UploadSession::whereNotNull('completed_at')
            ->where('completed_at', '<', now()->subWeek())
            ->delete();

        expect(UploadSession::find($old->id))->toBeNull();
    });
});
