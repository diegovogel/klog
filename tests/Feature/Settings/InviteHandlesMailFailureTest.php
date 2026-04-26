<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserInviteService;

it('redirects with an error when the invite service throws (e.g. mail outage)', function () {
    $stub = Mockery::mock(UserInviteService::class);
    $stub->shouldReceive('invite')->once()->andThrow(new \RuntimeException('SMTP outage'));
    $this->app->instance(UserInviteService::class, $stub);

    $admin = User::factory()->admin()->create(['password' => 'password']);

    $this->actingAs($admin)->withSession(['two_factor_confirmed' => true])
        ->from(route('settings'))
        ->post(route('settings.users.invite'), [
            'name' => 'Newbie',
            'email' => 'newbie@example.com',
            'role' => UserRole::MEMBER->value,
        ])
        ->assertRedirect(route('settings'))
        ->assertSessionHasErrors('invite');
});
