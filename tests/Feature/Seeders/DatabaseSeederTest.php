<?php

use App\Models\Media;
use App\Models\Memory;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('creates a default user', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1);
    expect(User::first()->email)->toBe('diego@birdboar.co');
    expect(User::first()->name)->toBe('Diego Vogel');
});

it('seeds all related data', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Memory::count())->toBeGreaterThan(0);
    expect(Tag::count())->toBeGreaterThan(0);
    expect(Media::count())->toBeGreaterThan(0);
});

it('creates memories with associated web clippings', function () {
    $this->seed(DatabaseSeeder::class);

    $memoriesWithClippings = Memory::has('webClippings')->count();

    expect($memoriesWithClippings)->toBeGreaterThan(0);
});

it('creates memories with associated tags', function () {
    $this->seed(DatabaseSeeder::class);

    $memoriesWithTags = Memory::has('tags')->count();

    expect($memoriesWithTags)->toBeGreaterThan(0);
});

it('creates memories with associated media', function () {
    $this->seed(DatabaseSeeder::class);

    $memoriesWithMedia = Memory::has('media')->count();

    expect($memoriesWithMedia)->toBeGreaterThan(0);
});
