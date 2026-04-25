<?php

use App\Models\User;
use App\Models\UserInvite;

describe('show', function () {
    it('renders the form for a usable invite', function () {
        $user = User::factory()->create();
        $invite = UserInvite::factory()->for($user)->create();

        $this->get(route('invites.show', ['token' => $invite->token]))
            ->assertSuccessful()
            ->assertSee($user->email);
    });

    it('404s for an expired invite', function () {
        $user = User::factory()->create();
        $invite = UserInvite::factory()->for($user)->expired()->create();

        $this->get(route('invites.show', ['token' => $invite->token]))
            ->assertNotFound();
    });

    it('404s for an already-accepted invite', function () {
        $user = User::factory()->create();
        $invite = UserInvite::factory()->for($user)->accepted()->create();

        $this->get(route('invites.show', ['token' => $invite->token]))
            ->assertNotFound();
    });

    it('404s for an unknown token', function () {
        $this->get(route('invites.show', ['token' => 'nope']))
            ->assertNotFound();
    });
});

describe('accept', function () {
    it('sets password, marks invite accepted, and logs the user in', function () {
        $user = User::factory()->create();
        $invite = UserInvite::factory()->for($user)->create();

        $this->post(route('invites.accept', ['token' => $invite->token]), [
            'name' => 'Renamed',
            'password' => 'super-secret-12',
            'password_confirmation' => 'super-secret-12',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user->fresh());

        $fresh = $user->fresh();
        expect($fresh->name)->toBe('Renamed');
        expect(\Illuminate\Support\Facades\Hash::check('super-secret-12', $fresh->password))->toBeTrue();
        expect($invite->fresh()->isAccepted())->toBeTrue();
    });

    it('rejects accept for an expired invite', function () {
        $user = User::factory()->create();
        $invite = UserInvite::factory()->for($user)->expired()->create();

        $this->post(route('invites.accept', ['token' => $invite->token]), [
            'name' => 'X',
            'password' => 'super-secret-12',
            'password_confirmation' => 'super-secret-12',
        ])->assertNotFound();
    });
});
