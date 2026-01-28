<?php

use App\Models\Memory;
use Database\Seeders\MemorySeeder;

it('creates 400 memories', function () {
    $this->seed(MemorySeeder::class);

    expect(Memory::count())->toBe(400);
});

it('creates memories with captured_at dates spanning 400 days', function () {
    $this->seed(MemorySeeder::class);

    $oldestMemory = Memory::oldest('captured_at')->first();
    $newestMemory = Memory::latest('captured_at')->first();

    expect((int) $oldestMemory->captured_at->diffInDays($newestMemory->captured_at))->toBe(399);
});

it('creates memories with title and content from factory', function () {
    $this->seed(MemorySeeder::class);

    $memory = Memory::first();

    expect($memory->title)->not->toBeNull()
        ->and($memory->captured_at)->not->toBeNull();
});
