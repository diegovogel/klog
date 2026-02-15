<?php

use App\Models\Memory;
use App\Models\WebClipping;
use App\Services\MediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->service = new MediaStorageService;
});

describe('MediaStorageService', function () {
    it('stores a file to the local disk', function () {
        $memory = Memory::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg');

        $results = $this->service->storeForMemory($memory, [$file]);

        expect($results)->toHaveCount(1);
        Storage::disk('local')->assertExists($results[0]->path);
    });

    it('generates a UUID-based filename', function () {
        $memory = Memory::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg');

        $results = $this->service->storeForMemory($memory, [$file]);

        expect($results[0]->filename)
            ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });

    it('preserves the original filename', function () {
        $memory = Memory::factory()->create();
        $file = UploadedFile::fake()->image('vacation-sunset.jpg');

        $results = $this->service->storeForMemory($memory, [$file]);

        expect($results[0]->original_filename)->toBe('vacation-sunset.jpg');
    });

    it('stores files in the uploads/{year}/{month}/ path', function () {
        $memory = Memory::factory()->create();
        $file = UploadedFile::fake()->image('test.jpg');

        $results = $this->service->storeForMemory($memory, [$file]);

        $now = now();
        $expectedPrefix = sprintf('uploads/%s/%s/', $now->format('Y'), $now->format('m'));

        expect($results[0]->path)->toStartWith($expectedPrefix);
    });

    it('assigns order based on array position', function () {
        $memory = Memory::factory()->create();

        $files = [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
        ];

        $results = $this->service->storeForMemory($memory, $files);

        expect($results[0]->order)->toBe(0)
            ->and($results[1]->order)->toBe(1)
            ->and($results[2]->order)->toBe(2);
    });

    it('attaches media to the correct memory via morph relationship', function () {
        $memory = Memory::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg');

        $this->service->storeForMemory($memory, [$file]);

        expect($memory->media()->count())->toBe(1)
            ->and($memory->media->first()->mediable_type)->toBe($memory->getMorphClass());
    });

    it('derives image type from MIME', function () {
        $memory = Memory::factory()->create();
        $file = UploadedFile::fake()->image('photo.png');

        $results = $this->service->storeForMemory($memory, [$file]);

        expect($results[0]->type)->toBe('image');
    });

    it('resolves audio/webm when client reports audio but finfo detects video/webm', function () {
        $memory = Memory::factory()->create();

        // Simulate a browser-captured audio file: the client says audio/webm
        // but PHP's finfo detects the WebM container as video/webm.
        $file = UploadedFile::fake()->create('audio-recording.webm', 500, 'audio/webm');

        $results = $this->service->storeForMemory($memory, [$file]);

        expect($results[0]->mime_type)->toBe('audio/webm')
            ->and($results[0]->type)->toBe('audio');
    });

    it('resolves audio/mp4 when client reports audio but finfo detects video/mp4', function () {
        $memory = Memory::factory()->create();

        // Safari records audio as audio/mp4, but PHP's finfo detects
        // the MP4 container as video/mp4.
        $file = UploadedFile::fake()->create('audio-recording.mp4', 500, 'audio/mp4');

        $results = $this->service->storeForMemory($memory, [$file]);

        expect($results[0]->mime_type)->toBe('audio/mp4')
            ->and($results[0]->type)->toBe('audio');
    });
});

describe('storeScreenshotForClipping', function () {
    it('stores a screenshot file and creates a Media record', function () {
        $clipping = WebClipping::factory()->create();
        $tempPath = tempnam(sys_get_temp_dir(), 'klog_test_');
        file_put_contents($tempPath, str_repeat('x', 1024));

        $media = $this->service->storeScreenshotForClipping($clipping, $tempPath);

        expect($media)->not->toBeNull()
            ->and($media->original_filename)->toBe('screenshot.png')
            ->and($media->mime_type)->toBe('image/png')
            ->and($media->type)->toBe('image')
            ->and($media->disk)->toBe('local')
            ->and($media->size)->toBe(1024)
            ->and($media->order)->toBe(0);

        Storage::disk('local')->assertExists($media->path);

        @unlink($tempPath);
    });

    it('generates a UUID-based filename for screenshots', function () {
        $clipping = WebClipping::factory()->create();
        $tempPath = tempnam(sys_get_temp_dir(), 'klog_test_');
        file_put_contents($tempPath, 'fake-png');

        $media = $this->service->storeScreenshotForClipping($clipping, $tempPath);

        expect($media->filename)
            ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');

        @unlink($tempPath);
    });

    it('stores screenshots in the uploads/{year}/{month}/ path', function () {
        $clipping = WebClipping::factory()->create();
        $tempPath = tempnam(sys_get_temp_dir(), 'klog_test_');
        file_put_contents($tempPath, 'fake-png');

        $media = $this->service->storeScreenshotForClipping($clipping, $tempPath);

        $now = now();
        $expectedPrefix = sprintf('uploads/%s/%s/', $now->format('Y'), $now->format('m'));

        expect($media->path)->toStartWith($expectedPrefix)
            ->and($media->path)->toEndWith('.png');

        @unlink($tempPath);
    });

    it('attaches the screenshot to the correct clipping via morph relationship', function () {
        $clipping = WebClipping::factory()->create();
        $tempPath = tempnam(sys_get_temp_dir(), 'klog_test_');
        file_put_contents($tempPath, 'fake-png');

        $this->service->storeScreenshotForClipping($clipping, $tempPath);

        $clipping->refresh();
        expect($clipping->screenshot)->not->toBeNull()
            ->and($clipping->screenshot->mediable_type)->toBe($clipping->getMorphClass())
            ->and($clipping->screenshot->mediable_id)->toBe($clipping->id);

        @unlink($tempPath);
    });
});
