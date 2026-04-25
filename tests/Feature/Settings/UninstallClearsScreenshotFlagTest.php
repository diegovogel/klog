<?php

use App\Jobs\UninstallScreenshotsJob;
use App\Services\ScreenshotFeatureService;
use Illuminate\Support\Facades\Artisan;

it('disables the screenshots feature flag after a successful uninstall', function () {
    $feature = app(ScreenshotFeatureService::class);
    $feature->setEnabled(true);

    Artisan::shouldReceive('call')
        ->once()
        ->with('clippings:uninstall-screenshots')
        ->andReturn(0);

    Artisan::shouldReceive('output')->andReturn('done');

    (new UninstallScreenshotsJob)->handle($feature);

    expect($feature->isEnabled())->toBeFalse();
});

it('does not disable the flag if the uninstall fails', function () {
    $feature = app(ScreenshotFeatureService::class);
    $feature->setEnabled(true);

    Artisan::shouldReceive('call')
        ->once()
        ->with('clippings:uninstall-screenshots')
        ->andReturn(1);

    Artisan::shouldReceive('output')->andReturn('boom');

    (new UninstallScreenshotsJob)->handle($feature);

    expect($feature->isEnabled())->toBeTrue();
});
