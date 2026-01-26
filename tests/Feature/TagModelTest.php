<?php

use App\Models\Memory;
use App\Models\Tag;

it('should be tied to many memories', function () {
    $memories = Memory::factory()->count(3)->create();
    $tag = Tag::factory()->create();

    foreach ($memories as $memory) {
        $memory->syncTagNames([$tag->name]);
    }

    expect($tag->memories()->count())->toBe(3);
});
