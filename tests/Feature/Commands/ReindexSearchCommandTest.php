<?php

use App\Models\Memory;
use Illuminate\Support\Facades\DB;

it('rebuilds the index for every memory', function () {
    Memory::factory()->count(3)->create();
    DB::table('memories_fts')->delete();

    $this->artisan('search:reindex')
        ->expectsOutputToContain('Indexed 3 memories.')
        ->assertSuccessful();

    expect(DB::table('memories_fts')->count())->toBe(3);
});

it('reports zero when there are no memories to index', function () {
    DB::table('memories_fts')->delete();

    $this->artisan('search:reindex')
        ->expectsOutputToContain('Indexed 0 memories.')
        ->assertSuccessful();
});
