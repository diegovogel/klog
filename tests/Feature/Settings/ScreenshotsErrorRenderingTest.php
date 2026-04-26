<?php

use App\Models\User;
use App\Services\ScreenshotFeatureService;

it('renders a screenshots error on the settings page when reservation is rejected', function () {
    $admin = User::factory()->admin()->create(['password' => 'password']);
    $this->actingAs($admin)->withSession(['two_factor_confirmed' => true]);

    // Pre-reserve so the second click is rejected.
    app(ScreenshotFeatureService::class)->tryReserve('install');

    $this->post(route('settings.screenshots.install'))
        ->assertRedirect(route('settings'));

    $this->get(route('settings'))
        ->assertSuccessful()
        ->assertSee('A screenshot operation is already in progress');
});
