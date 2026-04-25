<?php

use App\Services\ScreenshotFeatureService;

it('lets a new operation start after the previous one finished successfully', function () {
    $feature = app(ScreenshotFeatureService::class);

    expect($feature->tryReserve('install'))->toBeTrue();
    $feature->markStatus('success', 'done', 'install');

    expect($feature->tryReserve('uninstall'))->toBeTrue();
});

it('lets a new operation start after the previous one failed', function () {
    $feature = app(ScreenshotFeatureService::class);

    expect($feature->tryReserve('install'))->toBeTrue();
    $feature->markStatus('failed', 'oops', 'install');

    expect($feature->tryReserve('install'))->toBeTrue();
});

it('still rejects a reservation while a job is queued or running', function () {
    $feature = app(ScreenshotFeatureService::class);

    expect($feature->tryReserve('install'))->toBeTrue();
    expect($feature->tryReserve('uninstall'))->toBeFalse();

    $feature->markStatus('running', 'working…', 'install');
    expect($feature->tryReserve('install'))->toBeFalse();
});
