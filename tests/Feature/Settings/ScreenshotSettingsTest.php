<?php

use App\Jobs\InstallScreenshotsJob;
use App\Jobs\UninstallScreenshotsJob;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\ScreenshotFeatureService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['password' => 'password']);
    $this->actingAs($this->admin)->withSession(['two_factor_confirmed' => true]);
});

describe('flag toggle', function () {
    it('toggles the screenshots_enabled flag', function () {
        $this->patch(route('settings.screenshots.update'), ['enabled' => '0'])
            ->assertRedirect(route('settings'));

        expect(AppSetting::getValue('screenshots_enabled'))->toBe('false');

        $this->patch(route('settings.screenshots.update'), ['enabled' => '1'])
            ->assertRedirect(route('settings'));

        expect(AppSetting::getValue('screenshots_enabled'))->toBe('true');
    });

    it('forbids non-admins', function () {
        $member = User::factory()->create();
        $this->actingAs($member)->withSession(['two_factor_confirmed' => true]);

        $this->patch(route('settings.screenshots.update'), ['enabled' => '1'])
            ->assertForbidden();
    });
});

describe('install/uninstall dispatch', function () {
    it('queues an install job and marks status', function () {
        Queue::fake();

        $this->post(route('settings.screenshots.install'))
            ->assertRedirect(route('settings'));

        Queue::assertPushed(InstallScreenshotsJob::class);

        $status = app(ScreenshotFeatureService::class)->status();
        expect($status['state'])->toBe('queued')
            ->and($status['action'])->toBe('install');
    });

    it('queues an uninstall job', function () {
        Queue::fake();

        $this->post(route('settings.screenshots.uninstall'))
            ->assertRedirect(route('settings'));

        Queue::assertPushed(UninstallScreenshotsJob::class);
    });

    it('exposes status as JSON', function () {
        app(ScreenshotFeatureService::class)->markStatus('running', 'Working…', 'install');

        $this->get(route('settings.screenshots.status'))
            ->assertSuccessful()
            ->assertJsonPath('status.state', 'running')
            ->assertJsonPath('status.action', 'install');
    });
});

describe('install job rollback', function () {
    it('rolls back a fresh install attempt when the command fails', function () {
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
});
