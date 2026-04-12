<?php

use App\Models\Child;
use App\Models\Memory;
use App\Models\User;

describe('auth', function () {
    it('redirects guests to login', function () {
        $this->get(route('search'))->assertRedirect(route('login'));
    });

    it('loads the search page for authenticated users', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('search'))
            ->assertSuccessful()
            ->assertSee('Search memories');
    });
});

describe('initial state', function () {
    it('shows all memories when no query or filters are present', function () {
        $user = User::factory()->create();
        Memory::factory()->count(3)->create();

        $response = $this->actingAs($user)->get(route('search'));

        $response->assertSuccessful();
        expect($response->viewData('results')->total())->toBe(3);
    });

    it('does not render the no-results message on the initial state', function () {
        $user = User::factory()->create();
        Memory::factory()->create();

        $this->actingAs($user)
            ->get(route('search'))
            ->assertDontSee('No memories match your search');
    });
});

describe('query + filter plumbing', function () {
    it('passes the query through to the service', function () {
        $user = User::factory()->create();
        Memory::factory()->create(['title' => 'Needle in haystack']);
        Memory::factory()->create(['title' => 'Unrelated']);

        $response = $this->actingAs($user)->get(route('search', ['q' => 'needle']));

        expect($response->viewData('results')->total())->toBe(1);
    });

    it('passes filters through to the service', function () {
        $user = User::factory()->create();
        Memory::factory()->create(['memory_date' => '2025-01-15']);
        Memory::factory()->create(['memory_date' => '2025-12-15']);

        $response = $this->actingAs($user)->get(route('search', [
            'from' => '2025-11-01',
        ]));

        expect($response->viewData('results')->total())->toBe(1);
    });

    it('rejects invalid filter values', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('search', ['types' => ['bogus']]))
            ->assertSessionHasErrors('types.0');
    });

    it('rejects date ranges where to is before from', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('search', ['from' => '2025-12-31', 'to' => '2025-01-01']))
            ->assertSessionHasErrors('to');
    });
});

describe('author filter visibility', function () {
    it('hides the author filter when only one user exists', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('search'));

        expect($response->viewData('showAuthorFilter'))->toBeFalse();
    });

    it('shows the author filter when multiple users exist', function () {
        User::factory()->count(2)->create();

        $response = $this->actingAs(User::first())->get(route('search'));

        expect($response->viewData('showAuthorFilter'))->toBeTrue();
    });
});

describe('no results state', function () {
    it('shows the no-matches message when a query has zero hits', function () {
        $user = User::factory()->create();
        Memory::factory()->create(['title' => 'Something']);

        $this->actingAs($user)
            ->get(route('search', ['q' => 'xyzzyunrelated']))
            ->assertSee('No memories match your search');
    });
});

describe('feed search field', function () {
    it('renders a search form on the feed that posts to the search route', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertSee('action="'.route('search').'"', false)
            ->assertSee('name="q"', false);
    });
});

describe('filter UI', function () {
    it('lists each child as a checkbox', function () {
        $user = User::factory()->create();
        Child::factory()->create(['name' => 'Alice']);
        Child::factory()->create(['name' => 'Bob']);

        $this->actingAs($user)
            ->get(route('search'))
            ->assertSee('Alice')
            ->assertSee('Bob');
    });
});
