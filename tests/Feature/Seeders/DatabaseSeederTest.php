<?php

use App\Models\Media;
use App\Models\Memory;
use App\Models\Tag;
use Database\Seeders\DatabaseSeeder;

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
