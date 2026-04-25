<?php

use App\Jobs\InstallScreenshotsJob;
use App\Services\ScreenshotFeatureService;
use Illuminate\Support\Facades\Artisan;

it('does NOT roll back when the screenshot package was already installed before the run', function () {
    $feature = \Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('isInstalled')->once()->andReturn(true);
    $feature->shouldReceive('markStatus')->withAnyArgs();

    Artisan::shouldReceive('call')
        ->once()
        ->with('clippings:install-screenshots')
        ->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('boom');

    // The crucial assertion: uninstall must NOT be called when packages were already present.
    Artisan::shouldNotReceive('call')->with('clippings:uninstall-screenshots');

    (new InstallScreenshotsJob)->handle($feature);
});

it('rolls back when the install was a fresh attempt that failed', function () {
    $feature = \Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('isInstalled')->once()->andReturn(false);
    $feature->shouldReceive('markStatus')->withAnyArgs();

    Artisan::shouldReceive('call')
        ->once()
        ->with('clippings:install-screenshots')
        ->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('boom');

    Artisan::shouldReceive('call')
        ->once()
        ->with('clippings:uninstall-screenshots')
        ->andReturn(0);

    (new InstallScreenshotsJob)->handle($feature);
});
