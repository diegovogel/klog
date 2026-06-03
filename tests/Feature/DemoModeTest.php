<?php

use App\Jobs\InstallScreenshotsJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

describe('one-click demo login', function () {
    it('404s when the app is not in demo mode', function () {
        config(['klog.is_demo' => false]);

        $this->post(route('demo.login'))->assertNotFound();
        $this->assertGuest();
    });

    it('logs in as the demo account and redirects when in demo mode', function () {
        config(['klog.is_demo' => true]);

        $demo = User::factory()->admin()->create(['email' => config('klog.demo_email')]);

        $this->post(route('demo.login'))->assertRedirect('/');
        $this->assertAuthenticatedAs($demo);
    });

    it('503s in demo mode when the demo account is missing', function () {
        config(['klog.is_demo' => true]);

        $this->post(route('demo.login'))->assertStatus(503);
        $this->assertGuest();
    });

    it('will not log in a deactivated demo account', function () {
        config(['klog.is_demo' => true]);

        User::factory()->admin()->deactivated()->create(['email' => config('klog.demo_email')]);

        $this->post(route('demo.login'))->assertStatus(503);
        $this->assertGuest();
    });
});

describe('block-in-demo guardrail', function () {
    beforeEach(function () {
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin)->withSession(['two_factor_confirmed' => true]);
    });

    it('blocks destructive admin actions in demo mode', function () {
        config(['klog.is_demo' => true]);
        Queue::fake();

        $this->from(route('settings'))
            ->post(route('settings.screenshots.install'))
            ->assertRedirect(route('settings'))
            ->assertSessionHas('error', 'This action is disabled in the demo.');

        Queue::assertNotPushed(InstallScreenshotsJob::class);
    });

    it('is a no-op outside demo mode', function () {
        config(['klog.is_demo' => false]);
        Queue::fake();

        $this->post(route('settings.screenshots.install'))
            ->assertRedirect(route('settings'));

        Queue::assertPushed(InstallScreenshotsJob::class);
    });
});

describe('demo:reset safety guard', function () {
    it('refuses to run outside demo mode', function () {
        config(['klog.is_demo' => false]);

        $this->artisan('demo:reset')
            ->expectsOutputToContain('only available when IS_DEMO=true')
            ->assertExitCode(1);
    });
});
