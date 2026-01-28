<?php

use App\Models\Memory;
use App\Models\WebClipping;
use Database\Seeders\MemorySeeder;
use Database\Seeders\WebClippingSeeder;

describe('when memories exist', function () {
    it('creates web clippings attached to memories', function () {
        $this->seed(MemorySeeder::class);
        $this->seed(WebClippingSeeder::class);

        expect(WebClipping::count())->toBeGreaterThan(0);
        expect(WebClipping::whereNotNull('memory_id')->count())->toBe(WebClipping::count());
    });

    it('attaches 1-3 clippings per memory that has clippings', function () {
        $this->seed(MemorySeeder::class);
        $this->seed(WebClippingSeeder::class);

        $memoriesWithClippings = Memory::has('webClippings')->get();

        foreach ($memoriesWithClippings as $memory) {
            expect($memory->webClippings()->count())->toBeGreaterThanOrEqual(1)
                ->and($memory->webClippings()->count())->toBeLessThanOrEqual(3);
        }
    });
});

describe('when no memories exist', function () {
    it('creates 100 standalone web clippings as fallback', function () {
        $this->seed(WebClippingSeeder::class);

        expect(WebClipping::count())->toBe(100);
    });
});
