<?php

use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->admin = User::factory()->admin()->create(['password' => 'password']);
    $this->actingAs($this->admin)->withSession(['two_factor_confirmed' => true]);
});

it('blocks resending an invite when the user has been deactivated', function () {
    $user = User::factory()->deactivated()->create();
    UserInvite::factory()->for($user)->create();

    $this->post(route('settings.users.resend-invite', $user))
        ->assertRedirect(route('settings'));

    Mail::assertNothingSent();

    $this->get(route('settings'))
        ->assertSuccessful()
        ->assertSee('Reactivate the user before resending their invite');
});
