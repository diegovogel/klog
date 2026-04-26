<?php

use App\Services\ScreenshotFeatureService;

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
