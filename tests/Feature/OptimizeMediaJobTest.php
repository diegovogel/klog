<?php

use App\Enums\MimeType;
use App\Enums\ProcessingStatus;
use App\Jobs\OptimizeMedia;
use App\Models\Media;
use App\Models\Memory;
use App\Models\User;
use App\Services\MediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

describe('OptimizeMedia job', function () {
    it('skips processing when media does not exist', function () {
        $job = new OptimizeMedia(999999);

        $job->handle(app(\App\Services\MediaOptimizationService::class));

        expect(Media::find(999999))->toBeNull();
    });

    it('skips processing when media is already complete', function () {
        $media = Media::factory()->image()->create([
            'processing_status' => ProcessingStatus::Complete,
        ]);

        $job = new OptimizeMedia($media->id);
        $job->handle(app(\App\Services\MediaOptimizationService::class));

        $media->refresh();
        expect($media->processing_status)->toBe(ProcessingStatus::Complete);
    });

    it('rethrows exceptions to allow queue retries', function () {
        $media = Media::factory()->heic()->create([
            'processing_status' => ProcessingStatus::Pending,
        ]);

        $service = Mockery::mock(\App\Services\MediaOptimizationService::class);
        $service->shouldReceive('needsImageConversion')->andReturn(true);
        $service->shouldReceive('convertImage')->andThrow(new RuntimeException('Imagick not available'));

        $job = new OptimizeMedia($media->id);

        expect(fn () => $job->handle($service))->toThrow(RuntimeException::class);

        $media->refresh();
        expect($media->processing_status)->toBe(ProcessingStatus::Processing);
    });

    it('sets status to failed via failed() after all retries exhausted', function () {
        $media = Media::factory()->heic()->create([
            'processing_status' => ProcessingStatus::Processing,
        ]);

        $job = new OptimizeMedia($media->id);
        $job->failed(new RuntimeException('Imagick not available'));

        $media->refresh();
        expect($media->processing_status)->toBe(ProcessingStatus::Failed);
    });

    it('transitions from pending to processing before conversion', function () {
        $media = Media::factory()->heic()->create([
            'processing_status' => ProcessingStatus::Pending,
        ]);

        $statuses = [];

        $service = Mockery::mock(\App\Services\MediaOptimizationService::class);
        $service->shouldReceive('needsImageConversion')->andReturn(true);
        $service->shouldReceive('needsVideoOptimization')->andReturn(false);
        $service->shouldReceive('convertImage')->andReturnUsing(function () use ($media, &$statuses) {
            $media->refresh();
            $statuses[] = $media->processing_status;
            $media->update(['processing_status' => ProcessingStatus::Complete]);
        });

        $job = new OptimizeMedia($media->id);
        $job->handle($service);

        expect($statuses[0])->toBe(ProcessingStatus::Processing);
    });
});

describe('optimization dispatch', function () {
    beforeEach(function () {
        Queue::fake();
        Storage::fake('local');
    });

    it('dispatches OptimizeMedia for HEIC uploads', function () {
        $memory = Memory::factory()->create();
        $file = UploadedFile::fake()->create('photo.heic', 500, 'image/heic');

        $service = new MediaStorageService;
        $service->storeForMemory($memory, [$file]);

        Queue::assertPushed(OptimizeMedia::class);
    });

    it('dispatches OptimizeMedia for MOV uploads', function () {
        $memory = Memory::factory()->create();
        $file = UploadedFile::fake()->create('video.mov', 500, 'video/quicktime');

        $service = new MediaStorageService;
        $service->storeForMemory($memory, [$file]);

        Queue::assertPushed(OptimizeMedia::class);
    });

    it('does not dispatch OptimizeMedia for JPEG uploads', function () {
        $memory = Memory::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg');

        $service = new MediaStorageService;
        $service->storeForMemory($memory, [$file]);

        Queue::assertNotPushed(OptimizeMedia::class);
    });

    it('does not dispatch OptimizeMedia for MP4 uploads', function () {
        $memory = Memory::factory()->create();
        $file = UploadedFile::fake()->create('video.mp4', 500, 'video/mp4');

        $service = new MediaStorageService;
        $service->storeForMemory($memory, [$file]);

        Queue::assertNotPushed(OptimizeMedia::class);
    });

    it('does not dispatch OptimizeMedia for audio uploads', function () {
        $memory = Memory::factory()->create();
        $file = UploadedFile::fake()->create('audio.mp3', 500, 'audio/mpeg');

        $service = new MediaStorageService;
        $service->storeForMemory($memory, [$file]);

        Queue::assertNotPushed(OptimizeMedia::class);
    });

    it('sets processing_status to pending when dispatching', function () {
        $memory = Memory::factory()->create();
        $file = UploadedFile::fake()->create('photo.heic', 500, 'image/heic');

        $service = new MediaStorageService;
        $results = $service->storeForMemory($memory, [$file]);

        expect($results[0]->processing_status)->toBe(ProcessingStatus::Pending);
    });

    it('leaves processing_status as complete for non-optimizable files', function () {
        $memory = Memory::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg');

        $service = new MediaStorageService;
        $results = $service->storeForMemory($memory, [$file]);

        expect($results[0]->processing_status)->toBe(ProcessingStatus::Complete);
    });

    it('dispatches for chunked uploads via attachUploadSessions', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $memory = Memory::factory()->create();

        $session = \App\Models\UploadSession::create([
            'id' => fake()->uuid(),
            'user_id' => $user->id,
            'original_filename' => 'photo.heic',
            'mime_type' => MimeType::HEIC->value,
            'total_size' => 5000,
            'total_chunks' => 1,
            'received_chunks' => 1,
            'received_chunk_indices' => [0],
            'disk' => 'local',
            'path' => 'uploads/2026/03/test-uuid.heic',
            'completed_at' => now(),
        ]);

        $service = new MediaStorageService;
        $service->attachUploadSessions($memory, [$session->id]);

        Queue::assertPushed(OptimizeMedia::class);
    });
});
