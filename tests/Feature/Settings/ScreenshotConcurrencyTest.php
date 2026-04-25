<?php

use App\Jobs\InstallScreenshotsJob;
use App\Jobs\UninstallScreenshotsJob;
use App\Models\User;
use App\Services\ScreenshotFeatureService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->admin = User::factory()->admin()->create(['password' => 'password']);
    $this->actingAs($this->admin)->withSession(['two_factor_confirmed' => true]);
});

it('refuses to enqueue a second install while one is already in progress', function () {
    $this->post(route('settings.screenshots.install'))
        ->assertRedirect(route('settings'));
    Queue::assertPushed(InstallScreenshotsJob::class, 1);

    $this->post(route('settings.screenshots.install'))
        ->assertRedirect(route('settings'));

    // Still only one job pushed — the second click was rejected.
    Queue::assertPushed(InstallScreenshotsJob::class, 1);

    expect(session('errors')->get('screenshots'))->not->toBeEmpty();
});

it('refuses to enqueue an uninstall while an install is already in progress', function () {
    $this->post(route('settings.screenshots.install'))->assertRedirect(route('settings'));

    $this->post(route('settings.screenshots.uninstall'))->assertRedirect(route('settings'));

    Queue::assertPushed(InstallScreenshotsJob::class, 1);
    Queue::assertNotPushed(UninstallScreenshotsJob::class);
});

it('allows a new install once the prior operation finishes', function () {
    $this->post(route('settings.screenshots.install'))->assertRedirect(route('settings'));

    // Simulate the worker finishing.
    app(ScreenshotFeatureService::class)->markStatus('success', 'done', 'install');
    app(ScreenshotFeatureService::class)->clearStatus();

    $this->post(route('settings.screenshots.install'))->assertRedirect(route('settings'));
    Queue::assertPushed(InstallScreenshotsJob::class, 2);
});
