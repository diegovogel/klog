<?php

use App\Models\Memory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('does not fire a per-card user count query on the feed', function () {
    $authors = User::factory()->count(3)->create();
    foreach ($authors as $author) {
        Memory::factory()->count(3)->create(['user_id' => $author->id]);
    }

    $viewer = $authors->first();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($viewer)->withSession(['two_factor_confirmed' => true])
        ->get('/')
        ->assertSuccessful();

    $countQueries = collect(DB::getQueryLog())
        ->filter(fn (array $entry) => str_contains($entry['query'], 'count(*)') && str_contains($entry['query'], 'users'));

    expect($countQueries)->toHaveCount(1);
});

it('does not N+1 the author relation on search results', function () {
    $authors = User::factory()->count(3)->create();
    foreach ($authors as $author) {
        Memory::factory()->count(3)->create(['user_id' => $author->id]);
    }

    $viewer = $authors->first();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($viewer)->withSession(['two_factor_confirmed' => true])
        ->get(route('search'))
        ->assertSuccessful();

    $userQueries = collect(DB::getQueryLog())
        ->filter(fn (array $entry) => preg_match('/from "users" where "users"\."id" (=|in)/', $entry['query']));

    // Eager-loaded authors should resolve in a single `where in` query, not one per memory.
    expect($userQueries->count())->toBeLessThanOrEqual(1);
});
