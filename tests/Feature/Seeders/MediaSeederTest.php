<?php

use App\Models\Media;
use App\Models\Memory;
use App\Models\WebClipping;
use Database\Seeders\MediaSeeder;
use Database\Seeders\MemorySeeder;
use Database\Seeders\WebClippingSeeder;

describe('when memories exist', function () {
    it('creates media attached to memories', function () {
        $this->seed(MemorySeeder::class);
        $this->seed(MediaSeeder::class);

        expect(Media::count())->toBeGreaterThan(0);
        expect(Media::where('mediable_type', Memory::class)->count())->toBeGreaterThan(0);
    });

    it('ensures memories without content always have media', function () {
        Memory::factory()->count(10)->create(['content' => null]);

        $this->seed(MediaSeeder::class);

        $memoriesWithoutContent = Memory::whereNull('content')->get();

        foreach ($memoriesWithoutContent as $memory) {
            expect($memory->media()->count())->toBeGreaterThan(0);
        }
    });
});

describe('when web clippings exist', function () {
    it('creates a screenshot for each web clipping', function () {
        $this->seed(MemorySeeder::class);
        $this->seed(WebClippingSeeder::class);
        $this->seed(MediaSeeder::class);

        $webClippings = WebClipping::all();

        foreach ($webClippings as $clipping) {
            expect($clipping->screenshot)->not->toBeNull();
        }
    });
});

describe('when no memories or web clippings exist', function () {
    it('creates 100 standalone media items as fallback', function () {
        $this->seed(MediaSeeder::class);

        expect(Media::count())->toBe(100);
    });
});
