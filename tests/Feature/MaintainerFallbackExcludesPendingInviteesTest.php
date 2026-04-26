<?php

use App\Models\User;
use App\Models\UserInvite;
use App\Services\MaintainerResolverService;

it('excludes pending invitees from the maintainer fallback list', function () {
    $accepted = User::factory()->create(['email' => 'accepted@example.com']);
    $pending = User::factory()->create(['email' => 'pending@example.com']);
    UserInvite::factory()->for($pending)->create();

    $emails = app(MaintainerResolverService::class)->getUserEmailsInOrder();

    expect($emails)->toContain('accepted@example.com');
    expect($emails)->not->toContain('pending@example.com');
});

it('includes a user once their invite has been accepted', function () {
    $user = User::factory()->create(['email' => 'now-accepted@example.com']);
    UserInvite::factory()->for($user)->accepted()->create();

    $emails = app(MaintainerResolverService::class)->getUserEmailsInOrder();

    expect($emails)->toContain('now-accepted@example.com');
});
