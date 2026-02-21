<?php

use App\Models\Media;
use App\Models\WebClipping;
use App\Services\ScreenshotService;
use Illuminate\Support\Facades\Storage;

describe('clippings:screenshot', function () {
    it('fails when Browsershot is not installed', function () {
        $mock = Mockery::mock(ScreenshotService::class);
        $mock->shouldReceive('isAvailable')->once()->andReturn(false);
        $this->app->instance(ScreenshotService::class, $mock);

        $this->artisan('clippings:screenshot')
            ->expectsOutput('Browsershot is not installed. Run: php artisan clippings:install-screenshots')
            ->assertFailed();
    });

    it('reports when all clippings already have screenshots', function () {
        Storage::fake('local');

        $mock = Mockery::mock(ScreenshotService::class);
        $mock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->app->instance(ScreenshotService::class, $mock);

        $clipping = WebClipping::factory()->create();
        Media::factory()->image()->create([
            'mediable_type' => WebClipping::class,
            'mediable_id' => $clipping->id,
        ]);

        $this->artisan('clippings:screenshot')
            ->expectsOutput('All web clippings already have screenshots.')
            ->assertSuccessful();
    });

    it('captures screenshots for clippings without one', function () {
        Storage::fake('local');

        $mock = Mockery::mock(ScreenshotService::class);
        $mock->shouldReceive('isAvailable')->once()->andReturn(true);
        $mock->shouldReceive('capture')->twice()->andReturnUsing(function () {
            $path = tempnam(sys_get_temp_dir(), 'klog_test_');
            file_put_contents($path, str_repeat('x', 512));

            return $path;
        });
        $this->app->instance(ScreenshotService::class, $mock);

        WebClipping::factory()->count(2)->create();

        $this->artisan('clippings:screenshot --limit=0')
            ->expectsOutput('Found 2 web clipping(s) to screenshot.')
            ->expectsOutputToContain('Captured 2/2 screenshots. 0 failed.')
            ->assertSuccessful();

        expect(Media::where('mediable_type', WebClipping::class)->count())->toBe(2);
    });

    it('skips clippings that already have a screenshot', function () {
        Storage::fake('local');

        $mock = Mockery::mock(ScreenshotService::class);
        $mock->shouldReceive('isAvailable')->once()->andReturn(true);
        $mock->shouldReceive('capture')->once()->andReturnUsing(function () {
            $path = tempnam(sys_get_temp_dir(), 'klog_test_');
            file_put_contents($path, str_repeat('x', 512));

            return $path;
        });
        $this->app->instance(ScreenshotService::class, $mock);

        // One with screenshot, one without
        $withScreenshot = WebClipping::factory()->create();
        Media::factory()->image()->create([
            'mediable_type' => WebClipping::class,
            'mediable_id' => $withScreenshot->id,
        ]);
        WebClipping::factory()->create();

        $this->artisan('clippings:screenshot --limit=0')
            ->expectsOutput('Found 1 web clipping(s) to screenshot.')
            ->assertSuccessful();

        expect(Media::where('mediable_type', WebClipping::class)->count())->toBe(2);
    });

    it('handles capture failures gracefully and continues', function () {
        Storage::fake('local');

        $callCount = 0;
        $mock = Mockery::mock(ScreenshotService::class);
        $mock->shouldReceive('isAvailable')->once()->andReturn(true);
        $mock->shouldReceive('capture')->twice()->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                throw new \RuntimeException('Connection timed out');
            }

            $path = tempnam(sys_get_temp_dir(), 'klog_test_');
            file_put_contents($path, str_repeat('x', 512));

            return $path;
        });
        $this->app->instance(ScreenshotService::class, $mock);

        WebClipping::factory()->count(2)->create();

        $this->artisan('clippings:screenshot --limit=0')
            ->expectsOutput('Found 2 web clipping(s) to screenshot.')
            ->expectsOutputToContain('Captured 1/2 screenshots. 1 failed.')
            ->assertSuccessful();

        expect(Media::where('mediable_type', WebClipping::class)->count())->toBe(1);
    });

    it('respects the --limit option', function () {
        Storage::fake('local');

        $mock = Mockery::mock(ScreenshotService::class);
        $mock->shouldReceive('isAvailable')->once()->andReturn(true);
        $mock->shouldReceive('capture')->once()->andReturnUsing(function () {
            $path = tempnam(sys_get_temp_dir(), 'klog_test_');
            file_put_contents($path, str_repeat('x', 512));

            return $path;
        });
        $this->app->instance(ScreenshotService::class, $mock);

        WebClipping::factory()->count(3)->create();

        $this->artisan('clippings:screenshot --limit=1')
            ->expectsOutput('Found 1 web clipping(s) to screenshot.')
            ->expectsOutputToContain('2 clipping(s) still need screenshots.')
            ->assertSuccessful();

        expect(Media::where('mediable_type', WebClipping::class)->count())->toBe(1);
    });

    it('increments screenshot_attempts on each try', function () {
        Storage::fake('local');

        $mock = Mockery::mock(ScreenshotService::class);
        $mock->shouldReceive('isAvailable')->once()->andReturn(true);
        $mock->shouldReceive('capture')->once()->andThrow(new \RuntimeException('Failed'));
        $this->app->instance(ScreenshotService::class, $mock);

        $clipping = WebClipping::factory()->create();

        $this->artisan('clippings:screenshot')
            ->assertSuccessful();

        expect($clipping->fresh()->screenshot_attempts)->toBe(1);
    });

    it('skips clippings that have reached max screenshot attempts', function () {
        Storage::fake('local');

        $mock = Mockery::mock(ScreenshotService::class);
        $mock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->app->instance(ScreenshotService::class, $mock);

        WebClipping::factory()->create([
            'screenshot_attempts' => 14,
        ]);

        $this->artisan('clippings:screenshot')
            ->expectsOutput('All web clippings already have screenshots.')
            ->assertSuccessful();
    });
});
