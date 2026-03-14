<?php

use App\Models\UploadSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('chunked upload', function () {
    describe('init', function () {
        it('creates an upload session', function () {
            $user = User::factory()->create();

            $this->actingAs($user)
                ->postJson(route('uploads.init'), [
                    'original_filename' => 'beach.jpg',
                    'mime_type' => 'image/jpeg',
                    'total_size' => 5000000,
                    'total_chunks' => 3,
                ])
                ->assertCreated()
                ->assertJsonStructure(['upload_id']);

            expect(UploadSession::count())->toBe(1);

            $session = UploadSession::first();
            expect($session->original_filename)->toBe('beach.jpg')
                ->and($session->mime_type)->toBe('image/jpeg')
                ->and($session->total_size)->toBe(5000000)
                ->and($session->total_chunks)->toBe(3)
                ->and($session->received_chunks)->toBe(0)
                ->and($session->user_id)->toBe($user->id);
        });

        it('rejects unsupported MIME types', function () {
            $user = User::factory()->create();

            $this->actingAs($user)
                ->postJson(route('uploads.init'), [
                    'original_filename' => 'doc.pdf',
                    'mime_type' => 'application/pdf',
                    'total_size' => 1000,
                    'total_chunks' => 1,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('mime_type');
        });

        it('rejects files exceeding the configured max size', function () {
            $user = User::factory()->create();

            $maxSize = config('klog.uploads.max_file_size', 500 * 1024 * 1024);

            $this->actingAs($user)
                ->postJson(route('uploads.init'), [
                    'original_filename' => 'huge.mp4',
                    'mime_type' => 'video/mp4',
                    'total_size' => $maxSize + 1,
                    'total_chunks' => 250,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('total_size');
        });

        it('requires authentication', function () {
            $this->postJson(route('uploads.init'), [
                'original_filename' => 'beach.jpg',
                'mime_type' => 'image/jpeg',
                'total_size' => 5000,
                'total_chunks' => 1,
            ])->assertUnauthorized();
        });
    });

    describe('chunk', function () {
        it('accepts a chunk and stores it', function () {
            Storage::fake('local');
            $user = User::factory()->create();
            $session = UploadSession::create([
                'user_id' => $user->id,
                'original_filename' => 'video.mp4',
                'mime_type' => 'video/mp4',
                'total_size' => 4000000,
                'total_chunks' => 2,
            ]);

            $chunk = UploadedFile::fake()->create('chunk.bin', 2000);

            $this->actingAs($user)
                ->postJson(route('uploads.chunk', $session), [
                    'chunk' => $chunk,
                    'chunk_index' => 0,
                ])
                ->assertSuccessful()
                ->assertJson(['received' => 1, 'total' => 2, 'complete' => false]);

            Storage::disk('local')->assertExists($session->chunksDirectory().'0.part');
        });

        it('increments received chunks counter', function () {
            Storage::fake('local');
            $user = User::factory()->create();
            $session = UploadSession::create([
                'user_id' => $user->id,
                'original_filename' => 'video.mp4',
                'mime_type' => 'video/mp4',
                'total_size' => 4000000,
                'total_chunks' => 2,
            ]);

            $chunk = UploadedFile::fake()->create('chunk.bin', 2000);

            $this->actingAs($user)
                ->postJson(route('uploads.chunk', $session), [
                    'chunk' => $chunk,
                    'chunk_index' => 0,
                ]);

            $session->refresh();
            expect($session->received_chunks)->toBe(1)
                ->and($session->received_chunk_indices)->toBe([0]);
        });

        it('handles duplicate chunk indices idempotently', function () {
            Storage::fake('local');
            $user = User::factory()->create();
            $session = UploadSession::create([
                'user_id' => $user->id,
                'original_filename' => 'video.mp4',
                'mime_type' => 'video/mp4',
                'total_size' => 4000000,
                'total_chunks' => 2,
            ]);

            $chunk = UploadedFile::fake()->create('chunk.bin', 2000);

            // Send the same chunk index twice
            $this->actingAs($user)
                ->postJson(route('uploads.chunk', $session), [
                    'chunk' => $chunk,
                    'chunk_index' => 0,
                ]);

            $this->actingAs($user)
                ->postJson(route('uploads.chunk', $session), [
                    'chunk' => UploadedFile::fake()->create('chunk.bin', 2000),
                    'chunk_index' => 0,
                ]);

            $session->refresh();
            expect($session->received_chunks)->toBe(1);
        });

        it('assembles the file when all chunks are received', function () {
            Storage::fake('local');
            $user = User::factory()->create();

            $chunk1Content = str_repeat('A', 1000);
            $chunk2Content = str_repeat('B', 1000);
            $chunk1 = UploadedFile::fake()->createWithContent('chunk.bin', $chunk1Content);
            $chunk2 = UploadedFile::fake()->createWithContent('chunk.bin', $chunk2Content);

            $session = UploadSession::create([
                'user_id' => $user->id,
                'original_filename' => 'photo.jpg',
                'mime_type' => 'image/jpeg',
                'total_size' => strlen($chunk1Content) + strlen($chunk2Content),
                'total_chunks' => 2,
            ]);

            $this->actingAs($user)
                ->postJson(route('uploads.chunk', $session), [
                    'chunk' => $chunk1,
                    'chunk_index' => 0,
                ]);

            $response = $this->actingAs($user)
                ->postJson(route('uploads.chunk', $session), [
                    'chunk' => $chunk2,
                    'chunk_index' => 1,
                ]);

            $response->assertJson(['complete' => true]);

            $session->refresh();
            expect($session->completed_at)->not->toBeNull()
                ->and($session->path)->toStartWith('uploads/')
                ->and($session->path)->toEndWith('.jpg');

            Storage::disk('local')->assertExists($session->path);
        });

        it('rejects chunks for another user session', function () {
            Storage::fake('local');
            $owner = User::factory()->create();
            $other = User::factory()->create();

            $session = UploadSession::create([
                'user_id' => $owner->id,
                'original_filename' => 'video.mp4',
                'mime_type' => 'video/mp4',
                'total_size' => 4000000,
                'total_chunks' => 2,
            ]);

            $chunk = UploadedFile::fake()->create('chunk.bin', 2000);

            $this->actingAs($other)
                ->postJson(route('uploads.chunk', $session), [
                    'chunk' => $chunk,
                    'chunk_index' => 0,
                ])
                ->assertForbidden();
        });

        it('rejects chunk index out of range', function () {
            Storage::fake('local');
            $user = User::factory()->create();
            $session = UploadSession::create([
                'user_id' => $user->id,
                'original_filename' => 'video.mp4',
                'mime_type' => 'video/mp4',
                'total_size' => 4000000,
                'total_chunks' => 2,
            ]);

            $chunk = UploadedFile::fake()->create('chunk.bin', 2000);

            $this->actingAs($user)
                ->postJson(route('uploads.chunk', $session), [
                    'chunk' => $chunk,
                    'chunk_index' => 5,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('chunk_index');
        });

        it('rejects chunks for already completed sessions', function () {
            Storage::fake('local');
            $user = User::factory()->create();
            $session = UploadSession::create([
                'user_id' => $user->id,
                'original_filename' => 'photo.jpg',
                'mime_type' => 'image/jpeg',
                'total_size' => 1000,
                'total_chunks' => 1,
                'received_chunks' => 1,
                'received_chunk_indices' => [0],
                'completed_at' => now(),
                'path' => 'uploads/2026/03/test.jpg',
            ]);

            $chunk = UploadedFile::fake()->create('chunk.bin', 1);

            $this->actingAs($user)
                ->postJson(route('uploads.chunk', $session), [
                    'chunk' => $chunk,
                    'chunk_index' => 0,
                ])
                ->assertStatus(409);
        });
    });

    describe('cancel', function () {
        it('deletes chunks and session on cancel', function () {
            Storage::fake('local');
            $user = User::factory()->create();
            $session = UploadSession::create([
                'user_id' => $user->id,
                'original_filename' => 'video.mp4',
                'mime_type' => 'video/mp4',
                'total_size' => 4000000,
                'total_chunks' => 2,
            ]);

            Storage::disk('local')->makeDirectory($session->chunksDirectory());
            Storage::disk('local')->put($session->chunksDirectory().'0.part', 'data');

            $this->actingAs($user)
                ->deleteJson(route('uploads.cancel', $session))
                ->assertNoContent();

            expect(UploadSession::find($session->id))->toBeNull();
            Storage::disk('local')->assertMissing($session->chunksDirectory().'0.part');
        });

        it('rejects cancellation of another user session', function () {
            $owner = User::factory()->create();
            $other = User::factory()->create();

            $session = UploadSession::create([
                'user_id' => $owner->id,
                'original_filename' => 'video.mp4',
                'mime_type' => 'video/mp4',
                'total_size' => 4000000,
                'total_chunks' => 2,
            ]);

            $this->actingAs($other)
                ->deleteJson(route('uploads.cancel', $session))
                ->assertForbidden();

            expect(UploadSession::find($session->id))->not->toBeNull();
        });
    });

    describe('memory store integration', function () {
        it('creates a memory with pre-uploaded files via upload IDs', function () {
            Storage::fake('local');
            $user = User::factory()->create();

            $session = UploadSession::create([
                'user_id' => $user->id,
                'original_filename' => 'beach.jpg',
                'mime_type' => 'image/jpeg',
                'total_size' => 5000,
                'total_chunks' => 1,
                'received_chunks' => 1,
                'received_chunk_indices' => [0],
                'completed_at' => now(),
                'path' => 'uploads/2026/03/test-uuid.jpg',
            ]);

            Storage::disk('local')->put('uploads/2026/03/test-uuid.jpg', 'fake image data');

            $this->actingAs($user)
                ->post(route('memories.store'), [
                    'title' => 'Chunked upload memory',
                    'memory_date' => '2026-03-13',
                    'uploads' => [$session->id],
                ])
                ->assertRedirect('/');

            $memory = \App\Models\Memory::first();
            expect($memory->media)->toHaveCount(1);

            $media = $memory->media->first();
            expect($media->original_filename)->toBe('beach.jpg')
                ->and($media->mime_type)->toBe('image/jpeg')
                ->and($media->path)->toBe('uploads/2026/03/test-uuid.jpg')
                ->and($media->order)->toBe(0);
        });

        it('rejects upload IDs belonging to another user', function () {
            $owner = User::factory()->create();
            $other = User::factory()->create();

            $session = UploadSession::create([
                'user_id' => $owner->id,
                'original_filename' => 'beach.jpg',
                'mime_type' => 'image/jpeg',
                'total_size' => 5000,
                'total_chunks' => 1,
                'received_chunks' => 1,
                'received_chunk_indices' => [0],
                'completed_at' => now(),
                'path' => 'uploads/2026/03/test.jpg',
            ]);

            $this->actingAs($other)
                ->post(route('memories.store'), [
                    'title' => 'Stolen upload',
                    'memory_date' => '2026-03-13',
                    'uploads' => [$session->id],
                ])
                ->assertNotFound();
        });

        it('rejects incomplete upload IDs', function () {
            $user = User::factory()->create();

            $session = UploadSession::create([
                'user_id' => $user->id,
                'original_filename' => 'beach.jpg',
                'mime_type' => 'image/jpeg',
                'total_size' => 5000,
                'total_chunks' => 2,
                'received_chunks' => 1,
                'received_chunk_indices' => [0],
            ]);

            $this->actingAs($user)
                ->post(route('memories.store'), [
                    'title' => 'Incomplete upload',
                    'memory_date' => '2026-03-13',
                    'uploads' => [$session->id],
                ])
                ->assertNotFound();
        });
    });
});
