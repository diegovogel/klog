<?php

use App\Enums\UserRole;
use App\Jobs\InstallScreenshotsJob;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

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

    it('hides the one-click panel until the demo account exists', function () {
        config(['klog.is_demo' => true]);

        $this->get(route('login'))->assertOk()->assertDontSee('Enter demo');
    });

    it('shows the one-click panel once the demo account exists', function () {
        config(['klog.is_demo' => true]);
        User::factory()->admin()->create(['email' => config('klog.demo_email')]);

        $this->get(route('login'))->assertOk()->assertSee('Enter demo');
    });
});

describe('multipart upload cap', function () {
    // The chunked flow already reads max_file_size; the no-JS multipart fallback
    // must honour the same ceiling, or the demo's small cap is trivially bypassed.
    it('rejects a multipart file larger than the configured max_file_size', function () {
        Storage::fake('local');
        config(['klog.uploads.max_file_size' => 25 * 1024 * 1024]);
        $this->actingAs(User::factory()->create());

        $oversized = UploadedFile::fake()->image('big.jpg')->size(30 * 1024); // 30 MB

        $this->post(route('memories.store'), [
            'memory_date' => now()->format('Y-m-d'),
            'media' => [$oversized],
        ])->assertSessionHasErrors(['media.0' => 'Each file must be 25 MB or smaller.']);
    });

    it('accepts a multipart file within the configured max_file_size', function () {
        Storage::fake('local');
        config(['klog.uploads.max_file_size' => 25 * 1024 * 1024]);
        $this->actingAs(User::factory()->create());

        $ok = UploadedFile::fake()->image('ok.jpg')->size(5 * 1024); // 5 MB

        $this->post(route('memories.store'), [
            'memory_date' => now()->format('Y-m-d'),
            'media' => [$ok],
        ])->assertRedirect('/')->assertSessionHasNoErrors();
    });
});

describe('demo seeding', function () {
    it('seeds without collision when DEMO_EMAIL matches the co-parent fixture', function () {
        config(['klog.demo_email' => 'alex@klog.app']);
        Storage::fake('local');

        $this->seed(DemoSeeder::class);

        expect(User::count())->toBe(2)
            ->and(User::where('email', 'alex@klog.app')->first()->role)->toBe(UserRole::ADMIN);
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

    // Every settings mutation must be blocked in demo: the shared demo password
    // is published on the login page, so an unblocked mutation lets any visitor
    // lock everyone out or trigger side effects until the next reset.
    it('blocks every settings mutation in demo mode', function (string $method, string $routeName) {
        config(['klog.is_demo' => true]);

        $this->from(route('settings'))
            ->{$method}(route($routeName))
            ->assertRedirect(route('settings'))
            ->assertSessionHas('error', 'This action is disabled in the demo.');
    })->with([
        'account' => ['patch', 'settings.account.update'],
        'password' => ['patch', 'settings.password.update'],
        'log-out-other-devices' => ['post', 'settings.log-out-other-devices'],
        '2fa enable' => ['post', 'two-factor.enable'],
        '2fa disable' => ['post', 'two-factor.disable'],
        '2fa recovery codes' => ['post', 'two-factor.recovery-codes'],
        '2fa authenticator confirm' => ['post', 'two-factor.authenticator.confirm'],
        'maintainer email' => ['patch', 'settings.maintainer-email.update'],
        'two-factor expiration' => ['patch', 'settings.two-factor-expiration.update'],
        'screenshots toggle' => ['patch', 'settings.screenshots.update'],
    ]);

    it('does not change the shared password in demo mode (lock-out vector)', function () {
        config(['klog.is_demo' => true]);
        $original = $this->admin->password;

        $this->from(route('settings'))->patch(route('settings.password.update'), [
            'current_password' => 'password',
            'password' => 'hijacked-password',
            'password_confirmation' => 'hijacked-password',
        ])->assertRedirect(route('settings'));

        expect($this->admin->fresh()->password)->toBe($original);
    });

    it('does not auto-install screenshot packages via the toggle in demo mode', function () {
        config(['klog.is_demo' => true, 'queue.default' => 'database']);
        Queue::fake();

        $this->from(route('settings'))
            ->patch(route('settings.screenshots.update'), ['enabled' => '1'])
            ->assertRedirect(route('settings'))
            ->assertSessionHas('error', 'This action is disabled in the demo.');

        Queue::assertNotPushed(InstallScreenshotsJob::class);
    });
});

describe('demo:reset safety guard', function () {
    it('refuses to run outside demo mode', function () {
        config(['klog.is_demo' => false]);

        $this->artisan('demo:reset')
            ->expectsOutputToContain('only available when IS_DEMO=true')
            ->assertExitCode(1);
    });

    it('skips when another reset already holds the lock', function () {
        config(['klog.is_demo' => true]);

        // Hold the lock so the command can't acquire it and must skip the
        // destructive rebuild entirely (so this test never runs migrate:fresh).
        $lock = Cache::store('file')->lock('demo-reset', 600);
        expect($lock->get())->toBeTrue();

        try {
            $this->artisan('demo:reset')
                ->expectsOutputToContain('already running')
                ->assertExitCode(0);
        } finally {
            $lock->release();
        }
    });
});

describe('demo abuse throttling', function () {
    // The chunk endpoint must carry a throttle too: one upload session can
    // receive unlimited chunk writes, so leaving it uncapped defeats the demo
    // abuse protection even when uploads/init is throttled.
    it('throttles every upload + memory write route with the demo limiters', function () {
        $routes = app('router')->getRoutes();

        expect($routes->getByName('uploads.chunk')->gatherMiddleware())->toContain('throttle:demo-chunks')
            ->and($routes->getByName('uploads.init')->gatherMiddleware())->toContain('throttle:demo-writes')
            ->and($routes->getByName('uploads.cancel')->gatherMiddleware())->toContain('throttle:demo-writes')
            ->and($routes->getByName('memories.store')->gatherMiddleware())->toContain('throttle:demo-writes')
            ->and($routes->getByName('memories.destroy')->gatherMiddleware())->toContain('throttle:demo-writes');
    });

    it('makes the demo limiters unlimited outside demo mode', function () {
        config(['klog.is_demo' => false]);
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);

        foreach (['demo-writes', 'demo-chunks'] as $name) {
            $limit = (RateLimiter::limiter($name))($request);
            expect($limit->maxAttempts)->toBe(PHP_INT_MAX);
        }
    });

    it('caps the demo limiters in demo mode', function () {
        config(['klog.is_demo' => true]);
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);

        $writes = (RateLimiter::limiter('demo-writes'))($request);
        $chunks = (RateLimiter::limiter('demo-chunks'))($request);

        expect($writes->maxAttempts)->toBe(30)
            ->and($chunks->maxAttempts)->toBe(300);
    });
});
