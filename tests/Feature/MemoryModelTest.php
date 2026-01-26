<?php

use App\Models\Media;
use App\Models\Memory;
use App\Models\Tag;
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

describe('syncTagNames method', function () {
    it('should create new tags if they do not exist', function () {
        $memory = Memory::factory()->create();

        expect(Tag::count())->toBe(0);

        $memory->syncTagNames(['tag1', 'tag2', 'tag3']);

        expect(Tag::count())->toBe(3);
    });

    it('should associate existing tags without creating new ones', function () {
        $memory = Memory::factory()->create();
        $tag1 = Tag::findOrCreateByName('tag1');
        $tag2 = Tag::findOrCreateByName('tag2');

        expect(Tag::count())->toBe(2);

        $memory->syncTagNames(['tag1', 'tag2']);

        expect(Tag::count())->toBe(2)
            ->and($memory->tags()->get()->pluck('id')->toArray())->toEqual([
                $tag1->id,
                $tag2->id,
            ]);
    });

    it('should remove tags not included in sync list', function () {
        $memory = Memory::factory()->create();
        $tag1 = Tag::findOrCreateByName('tag1');
        $tag2 = Tag::findOrCreateByName('tag2');
        $memory->syncTagNames([$tag1->name, $tag2->name]);

        expect($memory->tags()->count())->toBe(2);
        $memory->syncTagNames([$tag1->name]);
        expect($memory->tags()->count())->toBe(1);
    });
});

describe('attachTagNames method', function () {
    it('should create new tags if they do not exist', function () {
        $memory = Memory::factory()->create();
        expect(Tag::count())->toBe(0);
        $memory->attachTagNames(['tag1', 'tag2']);
        expect(Tag::count())->toBe(2);
    });

    it('should associate existing tags without creating new ones', function () {
        $memory = Memory::factory()->create();
        $tag1 = Tag::findOrCreateByName('tag1');
        $tag2 = Tag::findOrCreateByName('tag2');

        expect($memory->tags()->count())->toBe(0);

        $memory->attachTagNames([$tag1->name, $tag2->name]);

        expect($memory->tags()->count())->toBe(2);
    });
});
