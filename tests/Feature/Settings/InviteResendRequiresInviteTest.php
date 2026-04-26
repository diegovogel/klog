<?php

use App\Mail\UserInvited;
use App\Models\User;
use App\Models\UserInvite;
use App\Services\UserInviteService;
use Illuminate\Support\Facades\Mail;

it('refuses to resend an invite for a user who never had one', function () {
    Mail::fake();

    $user = User::factory()->create();

    expect(fn () => app(UserInviteService::class)->resend($user))
        ->toThrow(\RuntimeException::class, 'not invited');

    Mail::assertNothingSent();
});

it('the route also rejects resend for an uninvited user', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create(['password' => 'password']);
    $userWithoutInvite = User::factory()->create();

    $this->actingAs($admin)->withSession(['two_factor_confirmed' => true])
        ->post(route('settings.users.resend-invite', $userWithoutInvite))
        ->assertRedirect(route('settings'));

    Mail::assertNothingSent();
});

it('still resends successfully when the user does have a pending invite', function () {
    Mail::fake();

    $user = User::factory()->create();
    $invite = UserInvite::factory()->for($user)->create();

    $new = app(UserInviteService::class)->resend($user);

    expect($new->token)->not->toBe($invite->token);
    Mail::assertSent(UserInvited::class);
});
