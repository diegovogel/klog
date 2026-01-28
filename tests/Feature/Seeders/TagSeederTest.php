<?php

use App\Models\Memory;
use App\Models\Tag;
use Database\Seeders\MemorySeeder;
use Database\Seeders\TagSeeder;

describe('when memories exist', function () {
    it('creates tags proportional to memory count', function () {
        $this->seed(MemorySeeder::class);
        $this->seed(TagSeeder::class);

        $expectedTagCount = ceil(Memory::count() / 10);

        expect(Tag::count())->toBe((int) $expectedTagCount);
    });

    it('attaches 0-5 tags to each memory', function () {
        $this->seed(MemorySeeder::class);
        $this->seed(TagSeeder::class);

        $memories = Memory::all();

        foreach ($memories as $memory) {
            expect($memory->tags()->count())->toBeLessThanOrEqual(5);
        }
    });

    it('creates tags with unique names', function () {
        $this->seed(MemorySeeder::class);
        $this->seed(TagSeeder::class);

        $tagNames = Tag::pluck('name');
        $uniqueNames = $tagNames->unique();

        expect($tagNames->count())->toBe($uniqueNames->count());
    });
});

describe('when no memories exist', function () {
    it('creates 100 standalone tags as fallback', function () {
        $this->seed(TagSeeder::class);

        expect(Tag::count())->toBe(100);
    });
});
