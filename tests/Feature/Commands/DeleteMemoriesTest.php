<?php

use App\Models\Media;
use App\Models\Memory;
use App\Models\WebClipping;
use Illuminate\Support\Facades\Storage;

describe('memory:delete', function () {
    it('soft-deletes all memories and their relations', function () {
        $memory = Memory::factory()->create();
        Media::factory()->image()->create(['mediable_id' => $memory->id, 'mediable_type' => $memory->getMorphClass()]);
        WebClipping::factory()->create(['memory_id' => $memory->id]);

        $this->artisan('memory:delete --no-interaction')
            ->expectsOutputToContain('1 memories deleted')
            ->assertSuccessful();

        expect(Memory::count())->toBe(0)
            ->and(Memory::withTrashed()->count())->toBe(1)
            ->and(Media::count())->toBe(0)
            ->and(Media::withTrashed()->count())->toBe(1)
            ->and(WebClipping::count())->toBe(0)
            ->and(WebClipping::withTrashed()->count())->toBe(1);
    });

    it('filters memories by --before date', function () {
        Memory::factory()->create(['memory_date' => '2024-01-15']);
        $kept = Memory::factory()->create(['memory_date' => '2024-06-15']);

        $this->artisan('memory:delete --before=2024-06-01 --no-interaction')
            ->expectsOutputToContain('1 memories deleted')
            ->assertSuccessful();

        expect(Memory::count())->toBe(1)
            ->and(Memory::first()->id)->toBe($kept->id);
    });

    it('permanently deletes with --force and removes files from disk', function () {
        Storage::fake('local');

        $memory = Memory::factory()->create();
        $media = Media::factory()->image()->create([
            'mediable_id' => $memory->id,
            'mediable_type' => $memory->getMorphClass(),
            'path' => 'uploads/2024/01/test.jpg',
        ]);
        Storage::disk('local')->put($media->path, 'fake-image-data');

        $this->artisan('memory:delete --force --no-interaction')
            ->expectsOutputToContain('1 memories deleted')
            ->assertSuccessful();

        expect(Memory::withTrashed()->count())->toBe(0)
            ->and(Media::withTrashed()->count())->toBe(0);
        Storage::disk('local')->assertMissing($media->path);
    });

    it('shows message when no memories match', function () {
        $this->artisan('memory:delete --before=2020-01-01 --no-interaction')
            ->expectsOutputToContain('No memories found')
            ->assertSuccessful();
    });

    it('asks for confirmation in interactive mode', function () {
        Memory::factory()->create();

        $this->artisan('memory:delete')
            ->expectsConfirmation(
                'This will delete 1 memories (all) and their media/clippings. Continue?',
                'no'
            )
            ->expectsOutputToContain('Cancelled')
            ->assertSuccessful();

        expect(Memory::count())->toBe(1);
    });

    it('proceeds when confirmation is accepted', function () {
        Memory::factory()->create();

        $this->artisan('memory:delete')
            ->expectsConfirmation(
                'This will delete 1 memories (all) and their media/clippings. Continue?',
                'yes'
            )
            ->expectsOutputToContain('1 memories deleted')
            ->assertSuccessful();

        expect(Memory::count())->toBe(0);
    });
});
