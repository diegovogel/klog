<?php

use App\Models\User;
use App\Services\MaintainerResolverService;

it('excludes deactivated users from the maintainer fallback list', function () {
    $first = User::factory()->deactivated()->create(['email' => 'deactivated@example.com']);
    $second = User::factory()->create(['email' => 'active@example.com']);

    $emails = app(MaintainerResolverService::class)->getUserEmailsInOrder();

    expect($emails)->toBe(['active@example.com']);
});
