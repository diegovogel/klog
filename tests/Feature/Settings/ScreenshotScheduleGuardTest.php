<?php

use App\Services\ScreenshotFeatureService;
use Illuminate\Console\Scheduling\Schedule;

function clippingsScreenshotEvent(): \Illuminate\Console\Scheduling\Event
{
    $events = app(Schedule::class)->events();

    foreach ($events as $event) {
        if (str_contains($event->command ?? '', 'clippings:screenshot')) {
            return $event;
        }
    }

    throw new \RuntimeException('clippings:screenshot schedule event is not registered.');
}

it('skips the daily clippings:screenshot run when the toolchain is not installed, even if enabled', function () {
    $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('isEnabled')->andReturn(true);
    $feature->shouldReceive('isInstalled')->andReturn(false);
    app()->instance(ScreenshotFeatureService::class, $feature);

    expect(clippingsScreenshotEvent()->filtersPass(app()))->toBeFalse();
});

it('runs the daily clippings:screenshot when both enabled and installed', function () {
    $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('isEnabled')->andReturn(true);
    $feature->shouldReceive('isInstalled')->andReturn(true);
    app()->instance(ScreenshotFeatureService::class, $feature);

    expect(clippingsScreenshotEvent()->filtersPass(app()))->toBeTrue();
});

it('skips the daily clippings:screenshot when disabled, regardless of install state', function () {
    $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('isEnabled')->andReturn(false);
    app()->instance(ScreenshotFeatureService::class, $feature);

    expect(clippingsScreenshotEvent()->filtersPass(app()))->toBeFalse();
});
