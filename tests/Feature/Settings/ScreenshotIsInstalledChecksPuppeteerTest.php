<?php

use App\Services\ScreenshotFeatureService;
use App\Services\ScreenshotService;

it('reports the toolchain as not installed when puppeteer node module is missing', function () {
    // Force ScreenshotService::isAvailable() (the Browsershot PHP class check)
    // to return true while the puppeteer node module is absent on disk.
    $svc = \Mockery::mock(ScreenshotService::class)->makePartial();
    $svc->shouldReceive('isAvailable')->andReturn(true);
    app()->instance(ScreenshotService::class, $svc);

    $puppeteerPath = base_path('node_modules/puppeteer');

    if (is_dir($puppeteerPath)) {
        // The host actually has puppeteer installed (e.g. on the dev box).
        // Move it aside for the duration of this test.
        $tempPath = $puppeteerPath.'.bak-test-'.uniqid();
        rename($puppeteerPath, $tempPath);

        try {
            expect(app(ScreenshotFeatureService::class)->isInstalled())->toBeFalse();
        } finally {
            rename($tempPath, $puppeteerPath);
        }
    } else {
        expect(app(ScreenshotFeatureService::class)->isInstalled())->toBeFalse();
    }
});

it('reports the toolchain as installed only when both Browsershot and puppeteer are present', function () {
    if (! is_dir(base_path('node_modules/puppeteer'))) {
        $this->markTestSkipped('puppeteer not installed in this environment');
    }

    $svc = \Mockery::mock(ScreenshotService::class)->makePartial();
    $svc->shouldReceive('isAvailable')->andReturn(true);
    app()->instance(ScreenshotService::class, $svc);

    expect(app(ScreenshotFeatureService::class)->isInstalled())->toBeTrue();
});
