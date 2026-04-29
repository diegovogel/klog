<?php

use App\Jobs\InstallScreenshotsJob;
use App\Services\ScreenshotFeatureService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->originalComposerJson = File::get(base_path('composer.json'));
    $this->originalPackageJson = File::get(base_path('package.json'));
});

afterEach(function () {
    File::put(base_path('composer.json'), $this->originalComposerJson);
    File::put(base_path('package.json'), $this->originalPackageJson);
});

function setComposerHas(bool $present): void
{
    $composer = json_decode(test()->originalComposerJson, true);
    if ($present) {
        $composer['require']['spatie/browsershot'] = '*';
    } else {
        unset($composer['require']['spatie/browsershot'], $composer['require-dev']['spatie/browsershot']);
    }
    File::put(base_path('composer.json'), json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function setNpmHas(bool $present): void
{
    $package = json_decode(test()->originalPackageJson, true);
    if ($present) {
        $package['dependencies']['puppeteer'] = '*';
    } else {
        unset($package['dependencies']['puppeteer'], $package['devDependencies']['puppeteer']);
    }
    File::put(base_path('package.json'), json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function fakeFailingInstall(): void
{
    Artisan::shouldReceive('call')->once()->with('clippings:install-screenshots')->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('boom');
}

it('does NOT remove anything when both packages were already installed before the run', function () {
    setComposerHas(true);
    setNpmHas(true);
    Process::fake();

    $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('markStatus')->withAnyArgs();

    fakeFailingInstall();

    (new InstallScreenshotsJob)->handle($feature);

    Process::assertNothingRan();
});

it('does NOT remove anything when only the composer package was already installed and the install added nothing new', function () {
    setComposerHas(true);
    setNpmHas(false);
    Process::fake();

    $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('markStatus')->withAnyArgs();

    fakeFailingInstall();

    (new InstallScreenshotsJob)->handle($feature);

    Process::assertNothingRan();
});

it('removes only the puppeteer package when the install added it on top of an existing composer install', function () {
    setComposerHas(true);
    setNpmHas(false);
    Process::fake();

    $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('markStatus')->withAnyArgs();

    Artisan::shouldReceive('call')
        ->once()
        ->with('clippings:install-screenshots')
        ->andReturnUsing(function () {
            // Simulate the install command adding puppeteer before failing
            // pipeline verification.
            setNpmHas(true);

            return 1;
        });
    Artisan::shouldReceive('output')->andReturn('pipeline failed');

    (new InstallScreenshotsJob)->handle($feature);

    Process::assertRan('npm uninstall puppeteer');
    Process::assertDidntRun('composer remove spatie/browsershot');
});

it('removes both packages when neither was present before and both were added', function () {
    setComposerHas(false);
    setNpmHas(false);
    Process::fake();

    $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('markStatus')->withAnyArgs();

    Artisan::shouldReceive('call')
        ->once()
        ->with('clippings:install-screenshots')
        ->andReturnUsing(function () {
            setComposerHas(true);
            setNpmHas(true);

            return 1;
        });
    Artisan::shouldReceive('output')->andReturn('pipeline failed');

    (new InstallScreenshotsJob)->handle($feature);

    Process::assertRan('composer remove spatie/browsershot');
    Process::assertRan('npm uninstall puppeteer');
});

it('removes nothing when neither package was present before and the install failed before adding anything', function () {
    setComposerHas(false);
    setNpmHas(false);
    Process::fake();

    $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('markStatus')->withAnyArgs();

    fakeFailingInstall();

    (new InstallScreenshotsJob)->handle($feature);

    Process::assertNothingRan();
});

it('skips installing when auto-enable was undone before the worker picked up the job', function () {
    $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('isEnabled')->andReturn(false);
    $feature->shouldReceive('clearStatus')->once();

    Artisan::spy();

    (new InstallScreenshotsJob(autoEnable: true))->handle($feature);

    Artisan::shouldNotHaveReceived('call');
});

it('still installs when auto-enable is set and the feature is still enabled', function () {
    setComposerHas(true);
    setNpmHas(true);

    $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('isEnabled')->andReturn(true);
    $feature->shouldReceive('markStatus')->withAnyArgs();

    Artisan::shouldReceive('call')->once()->with('clippings:install-screenshots')->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('');

    (new InstallScreenshotsJob(autoEnable: true))->handle($feature);
});
