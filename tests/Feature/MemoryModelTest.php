<?php

use App\Models\Media;
use App\Models\Memory;
use App\Models\WebClipping;

it('should have many media items', function () {
    $memory = Memory::factory()->create();
    Media::factory(3)->for($memory, 'mediable')->create();

    expect($memory->media()->count())->toBe(3);
});

it('should have many web clippings', function () {
    $memory = Memory::factory()->create();
    WebClipping::factory(3)->for($memory)->create();

    expect($memory->webClippings()->count())->toBe(3);
});

it('should have many tags', function () {
    $memory = Memory::factory()->create();
    $memory->syncTagNames(['tag1', 'tag2']);

    expect($memory->tags()->count())->toBe(2);
});
