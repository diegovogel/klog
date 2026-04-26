<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['password' => 'password']);
    $this->actingAs($this->user)->withSession(['two_factor_confirmed' => true]);
});

describe('account update', function () {
    it('updates name without password when email is unchanged', function () {
        $this->patch(route('settings.account.update'), [
            'name' => 'New Name',
            'email' => $this->user->email,
        ])->assertRedirect(route('settings'));

        expect($this->user->fresh()->name)->toBe('New Name');
    });

    it('requires current password to change email', function () {
        $this->patch(route('settings.account.update'), [
            'name' => $this->user->name,
            'email' => 'new@example.com',
        ])->assertSessionHasErrors('current_password', null, 'account');

        expect($this->user->fresh()->email)->not->toBe('new@example.com');
    });

    it('updates email with correct current password', function () {
        $this->patch(route('settings.account.update'), [
            'name' => $this->user->name,
            'email' => 'new@example.com',
            'current_password' => 'password',
        ])->assertRedirect(route('settings'));

        expect($this->user->fresh()->email)->toBe('new@example.com');
    });

    it('rejects email already in use', function () {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->patch(route('settings.account.update'), [
            'name' => $this->user->name,
            'email' => 'taken@example.com',
            'current_password' => 'password',
        ])->assertSessionHasErrors('email', null, 'account');
    });
});

describe('password update', function () {
    it('changes password with correct current password', function () {
        $this->patch(route('settings.password.update'), [
            'current_password' => 'password',
            'password' => 'new-secret-1234',
            'password_confirmation' => 'new-secret-1234',
        ])->assertRedirect(route('settings'));

        expect(\Illuminate\Support\Facades\Hash::check('new-secret-1234', $this->user->fresh()->password))->toBeTrue();
    });

    it('rejects wrong current password', function () {
        $this->patch(route('settings.password.update'), [
            'current_password' => 'wrong-pass',
            'password' => 'new-secret-1234',
            'password_confirmation' => 'new-secret-1234',
        ])->assertSessionHasErrors('current_password', null, 'password');
    });

    it('rejects mismatched confirmation', function () {
        $this->patch(route('settings.password.update'), [
            'current_password' => 'password',
            'password' => 'new-secret-1234',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password', null, 'password');
    });
});

describe('log out other devices', function () {
    it('requires current password', function () {
        $this->post(route('settings.log-out-other-devices'), [
            'password' => 'wrong',
        ])->assertSessionHasErrors('password', null, 'logout_others');
    });

    it('clears remembered devices on success', function () {
        $this->user->rememberedDevices()->create([
            'token_hash' => hash('sha256', 'abc'),
            'expires_at' => now()->addDays(10),
        ]);

        expect($this->user->rememberedDevices)->toHaveCount(1);

        $this->post(route('settings.log-out-other-devices'), [
            'password' => 'password',
        ])->assertRedirect(route('settings'));

        expect($this->user->fresh()->rememberedDevices)->toHaveCount(0);
    });
});
