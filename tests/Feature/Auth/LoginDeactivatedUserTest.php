<?php

use App\Models\User;

it('rejects login for deactivated users with generic invalid-credentials', function () {
    $user = User::factory()->deactivated()->create(['password' => 'secret123']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('accepts login again after reactivation', function () {
    $user = User::factory()->deactivated()->create(['password' => 'secret123']);

    $user->reactivate();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])->assertRedirect('/');

    $this->assertAuthenticatedAs($user->fresh());
});
