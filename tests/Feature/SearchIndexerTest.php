<?php

use App\Models\Memory;
use App\Models\WebClipping;
use App\Services\SearchIndexer;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->indexer = app(SearchIndexer::class);
});

describe('index', function () {
    it('inserts an fts row mirroring the memory', function () {
        $memory = Memory::factory()->create([
            'title' => 'Dinner at the diner',
            'content' => '<p>We had <strong>pancakes</strong>.</p>',
        ]);

        // The observer already indexed on create; re-run to verify idempotency.
        $this->indexer->index($memory);

        $row = DB::table('memories_fts')->where('rowid', $memory->id)->first();

        expect($row->title)->toBe('Dinner at the diner')
            // Tags are replaced with spaces to preserve word boundaries,
            // so "<strong>pancakes</strong>." becomes "pancakes ." with a
            // space before the period. FTS5 ignores punctuation, so this
            // is equivalent to "pancakes." for search purposes.
            ->and($row->content)->toBe('We had pancakes .')
            ->and($row->tag_names)->toBe('')
            ->and($row->clipping_urls)->toBe('');
    });

    it('stores tag names and clipping urls in the fts row', function () {
        $memory = Memory::factory()->create(['title' => 'Trip']);
        $memory->syncTagNames(['vacation', 'beach']);
        WebClipping::factory()->for($memory)->create(['url' => 'https://example.com/article']);

        $this->indexer->index($memory);

        $row = DB::table('memories_fts')->where('rowid', $memory->id)->first();

        expect($row->tag_names)->toContain('vacation')
            ->and($row->tag_names)->toContain('beach')
            ->and($row->clipping_urls)->toContain('example.com/article');
    });

    it('overwrites an existing row rather than duplicating it', function () {
        $memory = Memory::factory()->create(['title' => 'Original']);

        $memory->update(['title' => 'Updated']);

        $rows = DB::table('memories_fts')->where('rowid', $memory->id)->get();

        expect($rows)->toHaveCount(1)
            ->and($rows->first()->title)->toBe('Updated');
    });

    it('preserves word boundaries when stripping html from content', function () {
        // Naive strip_tags() would produce "helloworld" and "paragraphonefoo"
        // which breaks FTS tokenization.
        $memory = Memory::factory()->create([
            'title' => 'Boundary test',
            'content' => '<p>Hello<br>world</p><p>paragraph</p><p>one</p><div>foo</div>',
        ]);

        $this->indexer->index($memory);

        $row = DB::table('memories_fts')->where('rowid', $memory->id)->first();

        expect($row->content)->toBe('Hello world paragraph one foo');
    });

    it('indexes html-stripped content in a way that lets FTS match individual words', function () {
        $memory = Memory::factory()->create([
            'title' => 'Boundary search',
            'content' => '<p>Hello<br>world</p>',
        ]);

        $results = app(\App\Services\SearchService::class)->search('world', []);

        expect($results->total())->toBe(1)
            ->and($results->first()->id)->toBe($memory->id);
    });
});

describe('extractText', function () {
    it('replaces tags with spaces to preserve word boundaries', function () {
        expect(\App\Services\SearchIndexer::extractText('hello<br>world'))->toBe('hello world');
    });

    it('decodes html entities', function () {
        expect(\App\Services\SearchIndexer::extractText('caf&eacute; &amp; cream'))->toBe('café & cream');
    });

    it('collapses runs of whitespace', function () {
        expect(\App\Services\SearchIndexer::extractText('<p>one</p>  <p>two</p>'))->toBe('one two');
    });

    it('returns empty string for empty input', function () {
        expect(\App\Services\SearchIndexer::extractText(''))->toBe('');
    });
});

describe('remove', function () {
    it('deletes the row for a memory', function () {
        $memory = Memory::factory()->create();

        expect(DB::table('memories_fts')->where('rowid', $memory->id)->exists())->toBeTrue();

        $this->indexer->remove($memory);

        expect(DB::table('memories_fts')->where('rowid', $memory->id)->exists())->toBeFalse();
    });

    it('is a no-op when the memory has no row', function () {
        $memory = Memory::factory()->create();
        DB::table('memories_fts')->where('rowid', $memory->id)->delete();

        $this->indexer->remove($memory);

        expect(DB::table('memories_fts')->where('rowid', $memory->id)->exists())->toBeFalse();
    });
});

describe('rebuild', function () {
    it('reindexes every memory from scratch', function () {
        Memory::factory()->count(3)->create();
        DB::table('memories_fts')->delete();

        $count = $this->indexer->rebuild();

        expect($count)->toBe(3)
            ->and(DB::table('memories_fts')->count())->toBe(3);
    });

    it('drops stale rows when memories have been deleted', function () {
        Memory::factory()->count(2)->create();
        DB::table('memories_fts')->insert([
            'rowid' => 9999,
            'title' => 'Orphan',
            'content' => '',
            'tag_names' => '',
            'clipping_urls' => '',
        ]);

        $this->indexer->rebuild();

        expect(DB::table('memories_fts')->where('rowid', 9999)->exists())->toBeFalse()
            ->and(DB::table('memories_fts')->count())->toBe(2);
    });
});

describe('observer integration', function () {
    it('auto-indexes on memory create', function () {
        $memory = Memory::factory()->create(['title' => 'Auto-indexed']);

        $row = DB::table('memories_fts')->where('rowid', $memory->id)->first();

        expect($row)->not->toBeNull()
            ->and($row->title)->toBe('Auto-indexed');
    });

    it('auto-updates on memory update', function () {
        $memory = Memory::factory()->create(['title' => 'Before']);
        $memory->update(['title' => 'After']);

        $row = DB::table('memories_fts')->where('rowid', $memory->id)->first();

        expect($row->title)->toBe('After');
    });

    it('removes the fts row on soft delete', function () {
        $memory = Memory::factory()->create();
        $memory->delete();

        expect(DB::table('memories_fts')->where('rowid', $memory->id)->exists())->toBeFalse();
    });

    it('restores the fts row when a soft-deleted memory is restored', function () {
        $memory = Memory::factory()->create(['title' => 'Restored']);
        $memory->delete();
        $memory->restore();

        $row = DB::table('memories_fts')->where('rowid', $memory->id)->first();

        expect($row)->not->toBeNull()
            ->and($row->title)->toBe('Restored');
    });
});
