<?php

use App\Models\Memory;
use App\Services\MediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->service = new MediaStorageService;
});

describe('MediaStorageService', function () {
    it('stores a file to the public disk', function () {
        $memory = Memory::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg');

        $results = $this->service->storeForMemory($memory, [$file]);

        expect($results)->toHaveCount(1);
        Storage::disk('public')->assertExists($results[0]->path);
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
});
