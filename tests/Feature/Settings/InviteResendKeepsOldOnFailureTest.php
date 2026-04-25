<?php

use App\Mail\UserInvited;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\UserInviteService;
use Illuminate\Support\Facades\Mail;

it('keeps the old invite alive when mail send fails during resend', function () {
    $user = User::factory()->create();
    $original = UserInvite::factory()->for($user)->create();

    Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP outage'));

    expect(fn () => app(UserInviteService::class)->resend($user))
        ->toThrow(\RuntimeException::class);

    // The original invite must still be in the database — usable for retry.
    $current = $user->fresh()->invite;
    expect($current)->not->toBeNull();
    expect($current->token)->toBe($original->token);
});

it('replaces the invite when mail send succeeds', function () {
    Mail::fake();

    $user = User::factory()->create();
    $original = UserInvite::factory()->for($user)->create();

    $new = app(UserInviteService::class)->resend($user);

    expect($new->token)->not->toBe($original->token);
    expect(UserInvite::where('token', $original->token)->exists())->toBeFalse();

    Mail::assertSent(UserInvited::class);
});
