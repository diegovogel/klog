<?php

namespace App\Services;

use App\Enums\TwoFactorMethod;
use App\Models\TwoFactorRememberedDevice;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class TwoFactorService
{
    public function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function issueEmailCode(User $user): string
    {
        $code = $this->generateCode();

        Cache::put(
            $this->emailCodeCacheKey($user),
            Hash::make($code),
            now()->addMinutes(config('klog.two_factor.email_code_ttl', 10))
        );

        return $code;
    }

    public function verifyEmailCode(User $user, string $code): bool
    {
        $hashedCode = Cache::get($this->emailCodeCacheKey($user));

        if (! $hashedCode || ! Hash::check($code, $hashedCode)) {
            return false;
        }

        Cache::forget($this->emailCodeCacheKey($user));

        return true;
    }

    public function verify(User $user, string $code): bool
    {
        if ($user->usesTwoFactorMethod(TwoFactorMethod::EMAIL)) {
            return $this->verifyEmailCode($user, $code);
        }

        if ($user->usesTwoFactorMethod(TwoFactorMethod::AUTHENTICATOR)) {
            $authenticator = app(AuthenticatorService::class);

            if (! $authenticator->isAvailable()) {
                return false;
            }

            return $authenticator->verify($user->two_factor_secret, $code);
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function generateRecoveryCodes(): array
    {
        $count = config('klog.two_factor.recovery_code_count', 8);
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(5).'-'.Str::random(5));
        }

        return $codes;
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    public static function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code) => Hash::make($code), $codes);
    }

    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $hashedCodes = $user->two_factor_recovery_codes ?? [];

        foreach ($hashedCodes as $index => $hashedCode) {
            if (Hash::check(strtoupper($code), $hashedCode)) {
                unset($hashedCodes[$index]);
                $user->update([
                    'two_factor_recovery_codes' => array_values($hashedCodes),
                ]);

                return true;
            }
        }

        return false;
    }

    public function generateRememberToken(User $user): string
    {
        $token = Str::random(64);
        $days = app(TwoFactorConfigService::class)->rememberDays();

        $user->rememberedDevices()->create([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays($days),
        ]);

        return $token;
    }

    public function verifyRememberToken(User $user, ?string $token): bool
    {
        if (! $token) {
            return false;
        }

        $device = $user->rememberedDevices()
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();

        if (! $device) {
            return false;
        }

        $device->update(['last_used_at' => now()]);

        return true;
    }

    public function pruneExpiredRememberedDevices(): int
    {
        return TwoFactorRememberedDevice::query()
            ->where('expires_at', '<=', now())
            ->delete();
    }

    /**
     * @return list<string> Plain-text recovery codes (show once to user)
     */
    public function enable(User $user, TwoFactorMethod $method): array
    {
        $recoveryCodes = self::generateRecoveryCodes();

        $user->update([
            'two_factor_method' => $method,
            'two_factor_recovery_codes' => self::hashRecoveryCodes($recoveryCodes),
            'two_factor_confirmed_at' => now(),
        ]);

        return $recoveryCodes;
    }

    public function disable(User $user): void
    {
        $user->update([
            'two_factor_method' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $user->rememberedDevices()->delete();
    }

    public function isRateLimited(User $user): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->rateLimitKey($user),
            config('klog.two_factor.max_attempts', 5)
        );
    }

    public function hitRateLimit(User $user): void
    {
        RateLimiter::hit(
            $this->rateLimitKey($user),
            config('klog.two_factor.decay_minutes', 10) * 60
        );
    }

    public function clearRateLimit(User $user): void
    {
        RateLimiter::clear($this->rateLimitKey($user));
    }

    private function emailCodeCacheKey(User $user): string
    {
        return "two_factor_code:{$user->id}";
    }

    private function rateLimitKey(User $user): string
    {
        return "two_factor_challenge:{$user->id}";
    }
}
