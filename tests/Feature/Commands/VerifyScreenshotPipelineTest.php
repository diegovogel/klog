<?php

use App\Services\ScreenshotService;

describe('clippings:verify-pipeline', function () {
    it('succeeds when the pipeline returns true', function () {
        $service = Mockery::mock(ScreenshotService::class);
        $service->shouldReceive('testPipeline')->once()->andReturn(true);
        app()->instance(ScreenshotService::class, $service);

        $this->artisan('clippings:verify-pipeline')
            ->expectsOutput('Pipeline OK.')
            ->assertSuccessful();
    });

    it('reports a clear error when the pipeline returns false', function () {
        $service = Mockery::mock(ScreenshotService::class);
        $service->shouldReceive('testPipeline')->once()->andReturn(false);
        app()->instance(ScreenshotService::class, $service);

        $this->artisan('clippings:verify-pipeline')
            ->expectsOutputToContain('Browsershot saved no output')
            ->assertFailed();
    });

    it('surfaces the underlying exception message when testPipeline throws', function () {
        $service = Mockery::mock(ScreenshotService::class);
        $service->shouldReceive('testPipeline')
            ->once()
            ->andThrow(new \RuntimeException('Could not find Chromium executable at /opt/chromium/chrome'));
        app()->instance(ScreenshotService::class, $service);

        $this->artisan('clippings:verify-pipeline')
            ->expectsOutputToContain('Could not find Chromium executable at /opt/chromium/chrome')
            ->assertFailed();
    });

    it('also surfaces a previous exception message when present', function () {
        $previous = new \RuntimeException('cause-deep');
        $outer = new \RuntimeException('cause-outer', 0, $previous);

        $service = Mockery::mock(ScreenshotService::class);
        $service->shouldReceive('testPipeline')->once()->andThrow($outer);
        app()->instance(ScreenshotService::class, $service);

        $this->artisan('clippings:verify-pipeline')
            ->expectsOutputToContain('cause-outer')
            ->expectsOutputToContain('cause-deep')
            ->assertFailed();
    });
});
