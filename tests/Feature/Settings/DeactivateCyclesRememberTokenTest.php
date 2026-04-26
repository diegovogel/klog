<?php

use App\Models\User;

it('cycles the remember_token on deactivate, killing legacy remember-me cookies', function () {
    $user = User::factory()->create(['remember_token' => 'old-recaller']);

    $user->deactivate();

    $fresh = $user->fresh();
    expect($fresh->getRememberToken())->not->toBe('old-recaller');
    expect($fresh->getRememberToken())->not->toBeEmpty();
});
