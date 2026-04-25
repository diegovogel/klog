<?php

namespace App\Services;

use App\Models\AppSetting;

class TwoFactorConfigService
{
    public const SETTING_KEY = 'two_factor_remember_days';

    public const MIN_DAYS = 1;

    public const MAX_DAYS = 365;

    /**
     * Resolve the 2FA remember-me duration.
     *
     * Priority:
     * 1. app_settings value (Settings UI wins)
     * 2. TWO_FACTOR_REMEMBER_DAYS env / config default
     */
    public function rememberDays(): int
    {
        try {
            $stored = AppSetting::getValue(self::SETTING_KEY);
            if ($stored !== null && $stored !== '') {
                return (int) $stored;
            }
        } catch (\Throwable) {
            // DB not available; fall through
        }

        return (int) config('klog.two_factor.remember_days', 30);
    }

    public function saveRememberDays(int $days): void
    {
        $days = max(self::MIN_DAYS, min(self::MAX_DAYS, $days));
        AppSetting::setValue(self::SETTING_KEY, (string) $days);
    }
}
