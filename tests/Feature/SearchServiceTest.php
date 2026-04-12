<?php

use App\Models\Child;
use App\Models\Media;
use App\Models\Memory;
use App\Models\User;
use App\Models\WebClipping;
use App\Services\SearchService;
use Carbon\Carbon;

beforeEach(function () {
    $this->search = app(SearchService::class);
});

describe('buildMatchQuery', function () {
    it('strips fts5 operator characters', function () {
        expect($this->search->buildMatchQuery('"birthday" (party):'))->toBe('birthday* party*');
    });

    it('appends prefix marker to each token', function () {
        expect($this->search->buildMatchQuery('birth part'))->toBe('birth* part*');
    });

    it('returns empty string for whitespace-only input', function () {
        expect($this->search->buildMatchQuery('   '))->toBe('');
    });

    it('handles unicode letters and numbers', function () {
        expect($this->search->buildMatchQuery('café 2026'))->toBe('café* 2026*');
    });
});

describe('search by query', function () {
    it('matches memory title with stemming', function () {
        Memory::factory()->create(['title' => 'Dishes we cooked']);
        Memory::factory()->create(['title' => 'Unrelated']);

        $results = $this->search->search('dish', []);

        expect($results->total())->toBe(1)
            ->and($results->first()->title)->toBe('Dishes we cooked');
    });

    it('matches memory content', function () {
        Memory::factory()->create(['title' => 'A', 'content' => 'We had pancakes for breakfast']);
        Memory::factory()->create(['title' => 'B', 'content' => 'Pizza night']);

        $results = $this->search->search('pancake', []);

        expect($results->total())->toBe(1)
            ->and($results->first()->title)->toBe('A');
    });

    it('matches via tag names', function () {
        $match = Memory::factory()->create(['title' => 'Beach']);
        $match->syncTagNames(['vacation']);

        Memory::factory()->create(['title' => 'Unrelated']);

        $results = $this->search->search('vacation', []);

        expect($results->total())->toBe(1)
            ->and($results->first()->title)->toBe('Beach');
    });

    it('matches via web clipping urls', function () {
        $match = Memory::factory()->create(['title' => 'Bookmark']);
        WebClipping::factory()->for($match)->create(['url' => 'https://example.com/unicorn']);
        $match->reindexSearch();

        Memory::factory()->create(['title' => 'Unrelated']);

        $results = $this->search->search('unicorn', []);

        expect($results->total())->toBe(1)
            ->and($results->first()->title)->toBe('Bookmark');
    });

    it('supports prefix matching', function () {
        Memory::factory()->create(['title' => 'Birthday party']);

        $results = $this->search->search('birth', []);

        expect($results->total())->toBe(1);
    });

    it('returns empty results when nothing matches', function () {
        Memory::factory()->create(['title' => 'Apples']);

        $results = $this->search->search('xyzzy', []);

        expect($results->total())->toBe(0);
    });

    it('sanitizes fts5 operator injection', function () {
        Memory::factory()->create(['title' => 'safe']);

        $results = $this->search->search('"; DROP TABLE memories; --', []);

        // No exception; the sanitizer strips punctuation so this becomes
        // `drop* table* memories*` which matches nothing.
        expect($results->total())->toBe(0);
    });
});

describe('filters without query', function () {
    it('returns all memories when no query and no filters', function () {
        Memory::factory()->count(3)->create();

        $results = $this->search->search('', []);

        expect($results->total())->toBe(3);
    });

    it('filters by author', function () {
        $diego = User::factory()->create();
        $wife = User::factory()->create();

        Memory::factory()->for($diego)->create();
        Memory::factory()->for($wife)->create();
        Memory::factory()->for($wife)->create();

        $results = $this->search->search('', ['user_id' => $wife->id]);

        expect($results->total())->toBe(2);
    });

    it('filters by date range', function () {
        Memory::factory()->create(['memory_date' => '2025-01-15']);
        Memory::factory()->create(['memory_date' => '2025-06-15']);
        Memory::factory()->create(['memory_date' => '2025-12-15']);

        $results = $this->search->search('', [
            'from' => Carbon::parse('2025-05-01'),
            'to' => Carbon::parse('2025-07-01'),
        ]);

        expect($results->total())->toBe(1);
    });

    it('filters by children (whereHas)', function () {
        $alice = Child::factory()->create(['name' => 'Alice']);
        $bob = Child::factory()->create(['name' => 'Bob']);

        $m1 = Memory::factory()->create();
        $m1->children()->attach($alice);

        $m2 = Memory::factory()->create();
        $m2->children()->attach($bob);

        Memory::factory()->create(); // no children

        $results = $this->search->search('', ['children' => [$alice->id]]);

        expect($results->total())->toBe(1);
    });

    it('filters by type photo', function () {
        $photo = Memory::factory()->create(['content' => null]);
        Media::factory()->image()->for($photo, 'mediable')->create();

        Memory::factory()->create(['content' => 'text only']);

        $results = $this->search->search('', ['types' => ['photo']]);

        expect($results->total())->toBe(1);
    });

    it('filters by type text (non-empty content)', function () {
        Memory::factory()->create(['content' => 'hello world']);
        Memory::factory()->create(['content' => null]);
        Memory::factory()->create(['content' => '']);

        $results = $this->search->search('', ['types' => ['text']]);

        expect($results->total())->toBe(1);
    });

    it('filters by type webclip', function () {
        $withClip = Memory::factory()->create();
        WebClipping::factory()->for($withClip)->create();

        Memory::factory()->create();

        $results = $this->search->search('', ['types' => ['webclip']]);

        expect($results->total())->toBe(1);
    });

    it('combines type filters with OR logic', function () {
        $photo = Memory::factory()->create(['content' => null]);
        Media::factory()->image()->for($photo, 'mediable')->create();

        $video = Memory::factory()->create(['content' => null]);
        Media::factory()->video()->for($video, 'mediable')->create();

        $audio = Memory::factory()->create(['content' => null]);
        Media::factory()->audio()->for($audio, 'mediable')->create();

        $results = $this->search->search('', ['types' => ['photo', 'video']]);

        expect($results->total())->toBe(2);
    });
});

describe('query combined with filters', function () {
    it('intersects fts match with eloquent filters', function () {
        $wife = User::factory()->create();
        $husband = User::factory()->create();

        Memory::factory()->for($wife)->create(['title' => 'Birthday cake']);
        Memory::factory()->for($husband)->create(['title' => 'Birthday party']);

        $results = $this->search->search('birthday', ['user_id' => $wife->id]);

        expect($results->total())->toBe(1)
            ->and($results->first()->title)->toBe('Birthday cake');
    });
});

describe('pagination', function () {
    it('paginates results', function () {
        Memory::factory()->count(25)->create();

        $results = $this->search->search('', [], perPage: 10);

        expect($results->total())->toBe(25)
            ->and($results->perPage())->toBe(10)
            ->and($results->lastPage())->toBe(3);
    });
});
