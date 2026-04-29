<?php

use App\Models\User;
use App\Services\ScreenshotFeatureService;
use Illuminate\Support\Facades\Log;

it('allows re-reservation when the prior reservation is stuck in queued state', function () {
    $feature = app(ScreenshotFeatureService::class);

    expect($feature->tryReserve('install'))->toBeTrue();
    expect($feature->tryReserve('install'))->toBeFalse();

    // Manually fast-forward the recorded queued-at timestamp to simulate a
    // worker that never picked the job up.
    $stale = cache()->get(ScreenshotFeatureService::STATUS_CACHE_KEY);
    $stale['state_changed_at'] = time() - (ScreenshotFeatureService::STUCK_QUEUED_AFTER_SECONDS + 1);
    cache()->put(ScreenshotFeatureService::STATUS_CACHE_KEY, $stale, now()->addHour());

    expect($feature->tryReserve('uninstall'))->toBeTrue();
});

it('does NOT allow re-reservation while a worker is actively running within the threshold', function () {
    $feature = app(ScreenshotFeatureService::class);

    expect($feature->tryReserve('install'))->toBeTrue();
    $feature->markStatus('running', 'working…', 'install');

    // A running job that's only been going for less than the stuck-running
    // threshold should still hold the lock.
    $running = cache()->get(ScreenshotFeatureService::STATUS_CACHE_KEY);
    $running['state_changed_at'] = time() - (ScreenshotFeatureService::STUCK_RUNNING_AFTER_SECONDS - 60);
    cache()->put(ScreenshotFeatureService::STATUS_CACHE_KEY, $running, now()->addHour());

    expect($feature->tryReserve('uninstall'))->toBeFalse();
});

it('allows re-reservation when running has been stuck longer than the worker timeout', function () {
    $feature = app(ScreenshotFeatureService::class);

    expect($feature->tryReserve('install'))->toBeTrue();
    $feature->markStatus('running', 'working…', 'install');

    $stale = cache()->get(ScreenshotFeatureService::STATUS_CACHE_KEY);
    $stale['state_changed_at'] = time() - (ScreenshotFeatureService::STUCK_RUNNING_AFTER_SECONDS + 1);
    cache()->put(ScreenshotFeatureService::STATUS_CACHE_KEY, $stale, now()->addHour());

    expect($feature->tryReserve('uninstall'))->toBeTrue();
});

it('reports stuck via status() when queued past the threshold', function () {
    $feature = app(ScreenshotFeatureService::class);

    expect($feature->tryReserve('uninstall'))->toBeTrue();
    expect($feature->status()['state'])->toBe('queued');

    $stale = cache()->get(ScreenshotFeatureService::STATUS_CACHE_KEY);
    $stale['state_changed_at'] = time() - (ScreenshotFeatureService::STUCK_QUEUED_AFTER_SECONDS + 1);
    cache()->put(ScreenshotFeatureService::STATUS_CACHE_KEY, $stale, now()->addHour());

    $status = $feature->status();
    expect($status['state'])->toBe('stuck')
        ->and($status['action'])->toBe('uninstall')
        ->and($status['message'])->toContain('queue worker');
});

it('reports stuck via status() when running past the worker timeout', function () {
    $feature = app(ScreenshotFeatureService::class);

    $feature->markStatus('running', 'working…', 'install');

    $stale = cache()->get(ScreenshotFeatureService::STATUS_CACHE_KEY);
    $stale['state_changed_at'] = time() - (ScreenshotFeatureService::STUCK_RUNNING_AFTER_SECONDS + 1);
    cache()->put(ScreenshotFeatureService::STATUS_CACHE_KEY, $stale, now()->addHour());

    $status = $feature->status();
    expect($status['state'])->toBe('stuck')
        ->and($status['action'])->toBe('install');
});

it('exposes stuck state through the status JSON endpoint', function () {
    $admin = User::factory()->admin()->create(['password' => 'password']);
    $this->actingAs($admin)->withSession(['two_factor_confirmed' => true]);

    $feature = app(ScreenshotFeatureService::class);
    $feature->markStatus('queued', 'Waiting for worker…', 'uninstall');

    $stale = cache()->get(ScreenshotFeatureService::STATUS_CACHE_KEY);
    $stale['state_changed_at'] = time() - (ScreenshotFeatureService::STUCK_QUEUED_AFTER_SECONDS + 1);
    cache()->put(ScreenshotFeatureService::STATUS_CACHE_KEY, $stale, now()->addHour());

    $this->get(route('settings.screenshots.status'))
        ->assertSuccessful()
        ->assertJsonPath('status.state', 'stuck')
        ->assertJsonPath('status.action', 'uninstall');
});

it('logs a warning once per stuck event, ignoring repeat status() polls', function () {
    Log::spy();

    $feature = app(ScreenshotFeatureService::class);
    $feature->markStatus('queued', 'Waiting for worker…', 'uninstall');

    $stale = cache()->get(ScreenshotFeatureService::STATUS_CACHE_KEY);
    $stale['state_changed_at'] = time() - (ScreenshotFeatureService::STUCK_QUEUED_AFTER_SECONDS + 1);
    cache()->put(ScreenshotFeatureService::STATUS_CACHE_KEY, $stale, now()->addHour());

    $feature->status();
    $feature->status();
    $feature->status();

    Log::shouldHaveReceived('warning')
        ->once()
        ->with(\Mockery::pattern('/queue worker likely not running/'), \Mockery::on(function ($context) {
            return ($context['action'] ?? null) === 'uninstall'
                && ($context['previous_state'] ?? null) === 'queued';
        }));
});

it('renders the stuck alert on the settings page when an operation is stuck', function () {
    $admin = User::factory()->admin()->create(['password' => 'password']);
    $this->actingAs($admin)->withSession(['two_factor_confirmed' => true]);

    $feature = app(ScreenshotFeatureService::class);
    $feature->markStatus('queued', 'Waiting for worker…', 'uninstall');

    $stale = cache()->get(ScreenshotFeatureService::STATUS_CACHE_KEY);
    $stale['state_changed_at'] = time() - (ScreenshotFeatureService::STUCK_QUEUED_AFTER_SECONDS + 1);
    cache()->put(ScreenshotFeatureService::STATUS_CACHE_KEY, $stale, now()->addHour());

    $this->get(route('settings'))
        ->assertSuccessful()
        ->assertSee('Uninstall job stuck.')
        ->assertSee('queue worker')
        ->assertDontSee('Waiting for worker…');
});
