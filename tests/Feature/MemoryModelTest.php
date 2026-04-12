<?php

use App\Models\Media;
use App\Models\Memory;
use App\Models\Tag;
use App\Models\User;
use App\Models\WebClipping;

it('should have many media items', function () {
    $memory = Memory::factory()->create();
    Media::factory(3)->for($memory, 'mediable')->create();

    expect($memory->media()->count())->toBe(3);
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $memory = Memory::factory()->for($user)->create();

    expect($memory->user)->toBeInstanceOf(User::class)
        ->and($memory->user->id)->toBe($user->id);
});

describe('derived types', function () {
    it('should return empty array when no content, media, or web clippings exist', function () {
        $memory = Memory::factory()->create(['content' => null]);

        expect($memory->types)->toBe([]);
    });

    it('should include text when memory has content', function () {
        $memory = Memory::factory()->create(['content' => 'Some text content']);

        expect($memory->types)->toBe(['text']);
    });

    it('should include photo when memory has image media', function () {
        $memory = Memory::factory()->create(['content' => null]);
        Media::factory()->image()->for($memory, 'mediable')->create();

        expect($memory->types)->toBe(['photo']);
    });

    it('should include video when memory has video media', function () {
        $memory = Memory::factory()->create(['content' => null]);
        Media::factory()->video()->for($memory, 'mediable')->create();

        expect($memory->types)->toBe(['video']);
    });

    it('should include audio when memory has audio media', function () {
        $memory = Memory::factory()->create(['content' => null]);
        Media::factory()->audio()->for($memory, 'mediable')->create();

        expect($memory->types)->toBe(['audio']);
    });

    it('should include webclip when memory has web clippings', function () {
        $memory = Memory::factory()->create(['content' => null]);
        WebClipping::factory()->for($memory)->create();

        expect($memory->types)->toBe(['webclip']);
    });

    it('should include multiple types when memory has content, media, and web clippings', function () {
        $memory = Memory::factory()->create(['content' => 'Some text']);
        Media::factory()->image()->for($memory, 'mediable')->create();
        Media::factory()->video()->for($memory, 'mediable')->create();
        WebClipping::factory()->for($memory)->create();

        expect($memory->types)->toContain('text')
            ->toContain('webclip')
            ->toContain('photo')
            ->toContain('video')
            ->toHaveCount(4);
    });

    it('should not duplicate types when multiple media of same type exist', function () {
        $memory = Memory::factory()->create(['content' => null]);
        Media::factory()->image()->for($memory, 'mediable')->create();
        Media::factory()->image()->for($memory, 'mediable')->create();

        expect($memory->types)->toBe(['photo']);
    });
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
