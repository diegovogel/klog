<?php

use App\Enums\TwoFactorMethod;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->service = app(TwoFactorService::class);
});

describe('generateCode', function () {
    it('generates a 6-digit numeric string', function () {
        $code = $this->service->generateCode();

        expect($code)->toHaveLength(6)
            ->and($code)->toMatch('/^\d{6}$/');
    });

    it('pads codes shorter than 6 digits', function () {
        // Run many times to increase chance of hitting a low number
        $codes = collect(range(1, 50))->map(fn () => $this->service->generateCode());

        $codes->each(fn ($code) => expect($code)->toHaveLength(6));
    });
});

describe('email code', function () {
    it('issues and verifies an email code', function () {
        $user = User::factory()->create();

        $code = $this->service->issueEmailCode($user);

        expect($code)->toHaveLength(6)
            ->and($this->service->verifyEmailCode($user, $code))->toBeTrue();
    });

    it('invalidates the code after successful verification', function () {
        $user = User::factory()->create();

        $code = $this->service->issueEmailCode($user);

        expect($this->service->verifyEmailCode($user, $code))->toBeTrue()
            ->and($this->service->verifyEmailCode($user, $code))->toBeFalse();
    });

    it('rejects an incorrect code', function () {
        $user = User::factory()->create();

        $this->service->issueEmailCode($user);

        expect($this->service->verifyEmailCode($user, '000000'))->toBeFalse();
    });

    it('rejects when no code has been issued', function () {
        $user = User::factory()->create();

        expect($this->service->verifyEmailCode($user, '123456'))->toBeFalse();
    });

    it('rejects an expired code', function () {
        $user = User::factory()->create();

        $code = $this->service->issueEmailCode($user);

        $this->travel(11)->minutes();

        expect($this->service->verifyEmailCode($user, $code))->toBeFalse();
    });
});

describe('verify', function () {
    it('delegates to email verification for email method', function () {
        $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();

        $code = $this->service->issueEmailCode($user);

        expect($this->service->verify($user, $code))->toBeTrue();
    });

    it('returns false for unknown method', function () {
        $user = User::factory()->create([
            'two_factor_method' => null,
            'two_factor_confirmed_at' => null,
        ]);

        expect($this->service->verify($user, '123456'))->toBeFalse();
    });
});

describe('recovery codes', function () {
    it('generates the configured number of codes', function () {
        $codes = TwoFactorService::generateRecoveryCodes();

        expect($codes)->toHaveCount(config('klog.two_factor.recovery_code_count', 8));
    });

    it('generates codes in XXXXX-XXXXX format', function () {
        $codes = TwoFactorService::generateRecoveryCodes();

        foreach ($codes as $code) {
            expect($code)->toMatch('/^[A-Z0-9]{5}-[A-Z0-9]{5}$/');
        }
    });

    it('hashes each code individually', function () {
        $codes = TwoFactorService::generateRecoveryCodes();
        $hashed = TwoFactorService::hashRecoveryCodes($codes);

        expect($hashed)->toHaveCount(count($codes));

        foreach ($hashed as $hash) {
            expect(Hash::info($hash)['algoName'])->toBe('bcrypt');
        }
    });

    it('verifies a valid recovery code and consumes it', function () {
        $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();

        $codes = TwoFactorService::generateRecoveryCodes();
        $user->update([
            'two_factor_recovery_codes' => TwoFactorService::hashRecoveryCodes($codes),
        ]);

        expect($this->service->verifyRecoveryCode($user, $codes[0]))->toBeTrue();

        $user->refresh();
        expect($user->two_factor_recovery_codes)->toHaveCount(count($codes) - 1);
    });

    it('verifies recovery codes case-insensitively', function () {
        $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();

        $codes = TwoFactorService::generateRecoveryCodes();
        $user->update([
            'two_factor_recovery_codes' => TwoFactorService::hashRecoveryCodes($codes),
        ]);

        expect($this->service->verifyRecoveryCode($user, strtolower($codes[0])))->toBeTrue();
    });

    it('rejects an invalid recovery code', function () {
        $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();

        expect($this->service->verifyRecoveryCode($user, 'XXXXX-YYYYY'))->toBeFalse();
    });
});

describe('remember token', function () {
    it('generates and verifies a remember token', function () {
        $user = User::factory()->create();

        $token = $this->service->generateRememberToken($user);

        expect($token)->toHaveLength(64)
            ->and($this->service->verifyRememberToken($user->fresh(), $token))->toBeTrue();
    });

    it('keeps previously issued tokens valid when a new one is generated', function () {
        $user = User::factory()->create();

        $tokenA = $this->service->generateRememberToken($user);
        $tokenB = $this->service->generateRememberToken($user);

        expect($this->service->verifyRememberToken($user->fresh(), $tokenA))->toBeTrue()
            ->and($this->service->verifyRememberToken($user->fresh(), $tokenB))->toBeTrue();
    });

    it('updates last_used_at on a successful verification', function () {
        $user = User::factory()->create();

        $token = $this->service->generateRememberToken($user);

        expect($user->rememberedDevices()->first()->last_used_at)->toBeNull();

        $this->service->verifyRememberToken($user->fresh(), $token);

        expect($user->rememberedDevices()->first()->last_used_at)->not->toBeNull();
    });

    it('rejects an expired token', function () {
        $user = User::factory()->create();

        $token = $this->service->generateRememberToken($user);
        $days = config('klog.two_factor.remember_days', 30);

        $this->travel($days + 1)->days();

        expect($this->service->verifyRememberToken($user->fresh(), $token))->toBeFalse();
    });

    it('rejects an invalid token', function () {
        $user = User::factory()->create();

        $this->service->generateRememberToken($user);

        expect($this->service->verifyRememberToken($user->fresh(), 'wrong-token'))->toBeFalse();
    });

    it('rejects null token', function () {
        $user = User::factory()->create();

        expect($this->service->verifyRememberToken($user, null))->toBeFalse();
    });

    it('rejects when user has no stored token', function () {
        $user = User::factory()->create();

        expect($this->service->verifyRememberToken($user, 'some-token'))->toBeFalse();
    });

    it('prunes expired remembered devices', function () {
        $user = User::factory()->create();

        $this->service->generateRememberToken($user);
        $days = config('klog.two_factor.remember_days', 30);

        $this->travel($days + 1)->days();

        $this->service->generateRememberToken($user);

        expect($user->rememberedDevices()->count())->toBe(2);

        $pruned = $this->service->pruneExpiredRememberedDevices();

        expect($pruned)->toBe(1)
            ->and($user->rememberedDevices()->count())->toBe(1);
    });
});

describe('enable and disable', function () {
    it('enables two-factor and returns recovery codes', function () {
        $user = User::factory()->create();

        $codes = $this->service->enable($user, TwoFactorMethod::EMAIL);

        $user->refresh();

        expect($codes)->toHaveCount(config('klog.two_factor.recovery_code_count', 8))
            ->and($user->two_factor_method)->toBe(TwoFactorMethod::EMAIL)
            ->and($user->two_factor_confirmed_at)->not->toBeNull()
            ->and($user->two_factor_recovery_codes)->toHaveCount(count($codes));
    });

    it('disables two-factor and clears all columns and remembered devices', function () {
        $user = User::factory()->withTwoFactor(TwoFactorMethod::EMAIL)->create();
        $this->service->generateRememberToken($user);

        $this->service->disable($user);

        $user->refresh();

        expect($user->two_factor_method)->toBeNull()
            ->and($user->two_factor_secret)->toBeNull()
            ->and($user->two_factor_recovery_codes)->toBeNull()
            ->and($user->two_factor_confirmed_at)->toBeNull()
            ->and($user->rememberedDevices()->count())->toBe(0);
    });
});

describe('rate limiting', function () {
    it('is not rate limited initially', function () {
        $user = User::factory()->create();

        expect($this->service->isRateLimited($user))->toBeFalse();
    });

    it('becomes rate limited after max attempts', function () {
        $user = User::factory()->create();
        $maxAttempts = config('klog.two_factor.max_attempts', 5);

        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->service->hitRateLimit($user);
        }

        expect($this->service->isRateLimited($user))->toBeTrue();
    });

    it('clears the rate limit', function () {
        $user = User::factory()->create();
        $maxAttempts = config('klog.two_factor.max_attempts', 5);

        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->service->hitRateLimit($user);
        }

        $this->service->clearRateLimit($user);

        expect($this->service->isRateLimited($user))->toBeFalse();
    });
});
