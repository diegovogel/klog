<?php

use App\Models\Memory;
use Database\Seeders\MemorySeeder;

it('creates 400 memories', function () {
    $this->seed(MemorySeeder::class);

    expect(Memory::count())->toBe(400);
});

it('creates memories with memory_date spanning 400 days', function () {
    $this->seed(MemorySeeder::class);

    $oldestMemory = Memory::oldest('memory_date')->first();
    $newestMemory = Memory::latest('memory_date')->first();

    expect((int) round($oldestMemory->memory_date->diffInDays($newestMemory->memory_date)))->toBe(399);
});

it('creates memories with title and content from factory', function () {
    $this->seed(MemorySeeder::class);

    $memory = Memory::first();

    expect($memory->title)->not->toBeNull()
        ->and($memory->content)->not->toBeNull();
});
