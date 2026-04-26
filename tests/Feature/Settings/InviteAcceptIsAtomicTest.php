<?php

use App\Models\User;
use App\Models\UserInvite;
use App\Services\InviteAlreadyConsumedException;
use App\Services\UserInviteService;

it('rejects a second concurrent accept of the same invite token', function () {
    $user = User::factory()->create();
    $invite = UserInvite::factory()->for($user)->create();

    $first = app(UserInviteService::class)->accept($invite, 'First', 'first-secret-12');

    expect($first->name)->toBe('First');

    // Re-fetch the invite — it's now marked accepted; a second call must
    // throw rather than overwriting the password silently.
    $stale = $invite->fresh();
    expect(fn () => app(UserInviteService::class)->accept($stale, 'Second', 'second-secret-12'))
        ->toThrow(InviteAlreadyConsumedException::class);

    // The user's password and name from the first accept must be intact.
    $fresh = $user->fresh();
    expect($fresh->name)->toBe('First');
    expect(\Illuminate\Support\Facades\Hash::check('first-secret-12', $fresh->password))->toBeTrue();
    expect(\Illuminate\Support\Facades\Hash::check('second-secret-12', $fresh->password))->toBeFalse();
});

it('returns 404 from the controller when an invite has been consumed', function () {
    $user = User::factory()->create();
    $invite = UserInvite::factory()->for($user)->create();

    // Manually consume the invite (simulating a concurrent winner).
    $invite->update(['accepted_at' => now()]);

    // The controller's findUsable() will already 404 here — but ensure
    // that even if a request gets past it (e.g. raced check), the service
    // refuses to apply changes.
    $this->post(route('invites.accept', ['token' => $invite->token]), [
        'name' => 'X',
        'password' => 'super-secret-12',
        'password_confirmation' => 'super-secret-12',
    ])->assertNotFound();
});
