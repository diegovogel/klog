<?php

use App\Services\ScreenshotFeatureService;

it('exclusively reserves only one of two simultaneous reservation attempts', function () {
    $feature = app(ScreenshotFeatureService::class);

    // The first reservation succeeds; a second concurrent attempt while the
    // status is still queued/running must fail.
    expect($feature->tryReserve('install'))->toBeTrue();
    expect($feature->tryReserve('install'))->toBeFalse();
    expect($feature->tryReserve('uninstall'))->toBeFalse();
});
