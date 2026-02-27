<?php

use App\Models\Media;
use App\Models\Memory;
use App\Models\User;
use App\Models\WebClipping;

it('renders a screenshot as a clickable thumbnail that opens a dialog', function () {
    $user = User::factory()->create();
    $memory = Memory::factory()->create();
    $clipping = WebClipping::factory()->for($memory)->create();
    $screenshot = Media::factory()->image()->for($clipping, 'mediable')->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertStatus(200);
    $response->assertSee('memory-card__clipping-screenshot-btn', false);
    $response->assertSee('memory-card__clipping-thumbnail', false);
    $response->assertSee('clipping-screenshot-dialog', false);
    $response->assertSee(route('media.show', $screenshot->filename), false);
});

it('does not render screenshot markup when clipping has no screenshot', function () {
    $user = User::factory()->create();
    $memory = Memory::factory()->create();
    WebClipping::factory()->for($memory)->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertStatus(200);
    $response->assertDontSee('memory-card__clipping-screenshot-btn', false);
    $response->assertDontSee('clipping-screenshot-dialog', false);
});
