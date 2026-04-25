<?php

use App\Enums\UserRole;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['password' => 'password']);
    $this->actingAs($this->admin)->withSession(['two_factor_confirmed' => true]);
});

function inviteFormFragment(string $body): string
{
    if (! preg_match('/<select id="invite-role"[^>]*>(.*?)<\/select>/s', $body, $m)) {
        return '';
    }

    return $m[1];
}

it('preselects the member role on a fresh invite form', function () {
    $response = $this->get(route('settings'))->assertSuccessful();
    $fragment = inviteFormFragment((string) $response->getContent());

    expect($fragment)->toContain('<option value="member" selected>');
    expect($fragment)->not->toContain('<option value="admin" selected>');
});

it('preserves the submitted role on validation failure', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->from(route('settings'))->post(route('settings.users.invite'), [
        'name' => 'X',
        'email' => 'taken@example.com',
        'role' => UserRole::ADMIN->value,
    ]);

    $response = $this->get(route('settings'))->assertSuccessful();
    $fragment = inviteFormFragment((string) $response->getContent());

    expect($fragment)->toContain('<option value="admin" selected>');
});
