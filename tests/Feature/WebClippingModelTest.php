<?php

use App\Models\Media;
use App\Models\Memory;
use App\Models\WebClipping;

it('should belong to a memory', function () {
    $clipping = WebClipping::factory()->create();
    $memory = Memory::factory()->create();

    $clipping->memory()->associate($memory);
    $clipping->save();

    expect($clipping->memory_id)->toBe($memory->id);
});

it('should have a screenshot', function () {
    $clipping = WebClipping::factory()->create();
    $screenshot = Media::factory()->for($clipping, 'mediable')->create();

    expect($clipping->screenshot->id)->toBe($screenshot->id);
});
