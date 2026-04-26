<?php

use App\Jobs\InstallScreenshotsJob;
use App\Services\ScreenshotFeatureService;
use Illuminate\Support\Facades\Artisan;

it('does not overwrite a mid-flight admin disable when install completes', function () {
    $feature = \Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('isInstalled')->once()->andReturn(false);
    $feature->shouldReceive('markStatus')->withAnyArgs();

    // The job MUST NOT call setEnabled. If it does, this expectation will fail
    // because we declared the mock partial — calls to setEnabled would be allowed
    // but spy on them via `shouldNotReceive`.
    $feature->shouldNotReceive('setEnabled');

    Artisan::shouldReceive('call')
        ->once()
        ->with('clippings:install-screenshots')
        ->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('done');

    (new InstallScreenshotsJob)->handle($feature);
});

it('a fresh install still ends up effectively enabled via isEnabled() default', function () {
    $feature = app(ScreenshotFeatureService::class);

    // No stored value — default behavior should be enabled.
    expect($feature->isEnabled())->toBeTrue();
});
