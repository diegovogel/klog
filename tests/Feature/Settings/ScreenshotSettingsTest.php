<?php

use App\Jobs\InstallScreenshotsJob;
use App\Jobs\UninstallScreenshotsJob;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\ScreenshotFeatureService;
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

    it('auto-installs the toolchain when enabling on a system without it', function () {
        Queue::fake();
        config(['queue.default' => 'database']);

        $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
        $feature->shouldReceive('isInstalled')->andReturn(false);
        app()->instance(ScreenshotFeatureService::class, $feature);

        $this->patch(route('settings.screenshots.update'), ['enabled' => '1'])
            ->assertRedirect(route('settings'))
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Installing the toolchain'));

        expect(AppSetting::getValue('screenshots_enabled'))->toBe('true');
        Queue::assertPushed(InstallScreenshotsJob::class, fn (InstallScreenshotsJob $job) => $job->autoEnable === true);
    });

    it('does not auto-install when the toolchain is already installed', function () {
        Queue::fake();

        $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
        $feature->shouldReceive('isInstalled')->andReturn(true);
        app()->instance(ScreenshotFeatureService::class, $feature);

        $this->patch(route('settings.screenshots.update'), ['enabled' => '1'])
            ->assertRedirect(route('settings'))
            ->assertSessionHas('success', 'Screenshots enabled.');

        Queue::assertNotPushed(InstallScreenshotsJob::class);
    });

    it('does not auto-install when disabling', function () {
        Queue::fake();

        $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
        $feature->shouldReceive('isInstalled')->andReturn(false);
        app()->instance(ScreenshotFeatureService::class, $feature);

        $this->patch(route('settings.screenshots.update'), ['enabled' => '0'])
            ->assertRedirect(route('settings'))
            ->assertSessionHas('success', 'Screenshots disabled.');

        Queue::assertNotPushed(InstallScreenshotsJob::class);
    });

    it('skips auto-install on the sync queue driver to avoid blocking the request', function () {
        Queue::fake();
        config(['queue.default' => 'sync']);

        $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
        $feature->shouldReceive('isInstalled')->andReturn(false);
        app()->instance(ScreenshotFeatureService::class, $feature);

        $this->patch(route('settings.screenshots.update'), ['enabled' => '1'])
            ->assertRedirect(route('settings'))
            ->assertSessionHas('success', 'Screenshots enabled.');

        Queue::assertNotPushed(InstallScreenshotsJob::class);
    });

    it('skips auto-install when another operation is already in progress', function () {
        Queue::fake();
        config(['queue.default' => 'database']);

        // Pre-reserve to simulate an in-flight install/uninstall.
        app(ScreenshotFeatureService::class)->tryReserve('uninstall');

        $feature = Mockery::mock(ScreenshotFeatureService::class)->makePartial();
        $feature->shouldReceive('isInstalled')->andReturn(false);
        app()->instance(ScreenshotFeatureService::class, $feature);

        $this->patch(route('settings.screenshots.update'), ['enabled' => '1'])
            ->assertRedirect(route('settings'))
            ->assertSessionHas('success', 'Screenshots enabled.');

        Queue::assertNotPushed(InstallScreenshotsJob::class);
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

// Detailed install/rollback coverage lives in InstallRollbackTest.php, which
// exercises every combination of prior per-package presence.
