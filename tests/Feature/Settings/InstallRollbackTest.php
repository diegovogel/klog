<?php

use App\Jobs\InstallScreenshotsJob;
use App\Services\ScreenshotFeatureService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->originalComposerJson = File::get(base_path('composer.json'));
    $this->originalPackageJson = File::get(base_path('package.json'));
});

afterEach(function () {
    File::put(base_path('composer.json'), $this->originalComposerJson);
    File::put(base_path('package.json'), $this->originalPackageJson);
});

function withPackagesPresent(bool $composer, bool $npm): void
{
    $composerJson = json_decode(test()->originalComposerJson, true);
    if ($composer) {
        $composerJson['require']['spatie/browsershot'] = '*';
    } else {
        unset($composerJson['require']['spatie/browsershot'], $composerJson['require-dev']['spatie/browsershot']);
    }
    File::put(base_path('composer.json'), json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $packageJson = json_decode(test()->originalPackageJson, true);
    if ($npm) {
        $packageJson['dependencies']['puppeteer'] = '*';
    } else {
        unset($packageJson['dependencies']['puppeteer'], $packageJson['devDependencies']['puppeteer']);
    }
    File::put(base_path('package.json'), json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

it('does NOT roll back when both packages were already installed before the run', function () {
    withPackagesPresent(composer: true, npm: true);

    $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('markStatus')->withAnyArgs();

    Artisan::shouldReceive('call')->once()->with('clippings:install-screenshots')->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('boom');
    Artisan::shouldNotReceive('call')->with('clippings:uninstall-screenshots');

    (new InstallScreenshotsJob)->handle($feature);
});

it('does NOT roll back when only the composer package was already installed', function () {
    withPackagesPresent(composer: true, npm: false);

    $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('markStatus')->withAnyArgs();

    Artisan::shouldReceive('call')->once()->with('clippings:install-screenshots')->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('boom');
    Artisan::shouldNotReceive('call')->with('clippings:uninstall-screenshots');

    (new InstallScreenshotsJob)->handle($feature);
});

it('does NOT roll back when only the npm package was already installed', function () {
    withPackagesPresent(composer: false, npm: true);

    $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('markStatus')->withAnyArgs();

    Artisan::shouldReceive('call')->once()->with('clippings:install-screenshots')->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('boom');
    Artisan::shouldNotReceive('call')->with('clippings:uninstall-screenshots');

    (new InstallScreenshotsJob)->handle($feature);
});

it('rolls back when neither package was present and the install failed', function () {
    withPackagesPresent(composer: false, npm: false);

    $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('markStatus')->withAnyArgs();

    Artisan::shouldReceive('call')->once()->with('clippings:install-screenshots')->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('boom');
    Artisan::shouldReceive('call')->once()->with('clippings:uninstall-screenshots')->andReturn(0);

    (new InstallScreenshotsJob)->handle($feature);
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
    withPackagesPresent(composer: true, npm: true);

    $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
    $feature->shouldReceive('isEnabled')->andReturn(true);
    $feature->shouldReceive('markStatus')->withAnyArgs();

    Artisan::shouldReceive('call')->once()->with('clippings:install-screenshots')->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('');

    (new InstallScreenshotsJob(autoEnable: true))->handle($feature);
});
