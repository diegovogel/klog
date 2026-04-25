<?php

use App\Models\AppSetting;
use App\Models\User;
use App\Services\MaintainerResolverService;
use App\Services\TwoFactorConfigService;
use App\Services\TwoFactorService;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['password' => 'password']);
    $this->actingAs($this->admin)->withSession(['two_factor_confirmed' => true]);
});

describe('admin gating', function () {
    it('forbids non-admin members', function () {
        $member = User::factory()->create();
        $this->actingAs($member)->withSession(['two_factor_confirmed' => true]);

        $this->patch(route('settings.maintainer-email.update'), [
            'maintainer_email' => 'foo@bar.com',
        ])->assertForbidden();
    });
});

describe('maintainer email', function () {
    it('saves the maintainer email and prefers it over the env value', function () {
        config()->set('klog.maintainer_email', 'env@example.com');

        $this->patch(route('settings.maintainer-email.update'), [
            'maintainer_email' => 'ui@example.com',
        ])->assertRedirect(route('settings'));

        expect(AppSetting::getValue('maintainer_email'))->toBe('ui@example.com');
        expect(app(MaintainerResolverService::class)->resolve())->toBe('ui@example.com');
    });

    it('falls back to env when stored value is blank', function () {
        config()->set('klog.maintainer_email', 'env@example.com');
        AppSetting::setValue('maintainer_email', null);

        expect(app(MaintainerResolverService::class)->resolve())->toBe('env@example.com');
    });

    it('rejects invalid email', function () {
        $this->patch(route('settings.maintainer-email.update'), [
            'maintainer_email' => 'not-an-email',
        ])->assertSessionHasErrors('maintainer_email');
    });
});

describe('two-factor expiration', function () {
    it('saves a new value and uses it for new remembered devices', function () {
        config()->set('klog.two_factor.remember_days', 30);

        $this->patch(route('settings.two-factor-expiration.update'), [
            'remember_days' => 7,
        ])->assertRedirect(route('settings'));

        expect(app(TwoFactorConfigService::class)->rememberDays())->toBe(7);

        // Generating a new remember token uses the new duration.
        $user = User::factory()->create();
        app(TwoFactorService::class)->generateRememberToken($user);

        $device = $user->fresh()->rememberedDevices()->first();
        expect($device)->not->toBeNull()
            ->and($device->expires_at->isBetween(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()))
            ->toBeTrue();
    });

    it('does not retroactively change existing remembered devices', function () {
        $user = User::factory()->create();
        config()->set('klog.two_factor.remember_days', 30);
        app(TwoFactorService::class)->generateRememberToken($user);
        $original = $user->fresh()->rememberedDevices()->first()->expires_at;

        $this->patch(route('settings.two-factor-expiration.update'), [
            'remember_days' => 1,
        ])->assertRedirect(route('settings'));

        expect($user->fresh()->rememberedDevices()->first()->expires_at->equalTo($original))->toBeTrue();
    });

    it('rejects out-of-range values', function () {
        $this->patch(route('settings.two-factor-expiration.update'), [
            'remember_days' => 0,
        ])->assertSessionHasErrors('remember_days');

        $this->patch(route('settings.two-factor-expiration.update'), [
            'remember_days' => 1000,
        ])->assertSessionHasErrors('remember_days');
    });
});
