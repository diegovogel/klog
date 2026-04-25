<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;

class MaintainerResolverService
{
    private const SETTING_KEY = 'maintainer_email';

    /**
     * Resolve the maintainer email address.
     *
     * Priority:
     * 1. app_settings value (Settings UI wins)
     * 2. MAINTAINER_EMAIL env var (seed / default)
     * 3. null (caller must try users in order)
     */
    public function resolve(): ?string
    {
        try {
            $stored = AppSetting::getValue(self::SETTING_KEY);
            if ($stored !== null && $stored !== '') {
                return $stored;
            }
        } catch (\Throwable) {
            // DB may not be available during early bootstrap/logging
        }

        $envEmail = config('klog.maintainer_email');
        if ($envEmail !== null && $envEmail !== '') {
            return $envEmail;
        }

        return null;
    }

    public function save(?string $email): void
    {
        AppSetting::setValue(self::SETTING_KEY, $email);
    }

    /**
     * Get all user emails ordered by id (for fallback iteration).
     *
     * @return array<int, string>
     */
    public function getUserEmailsInOrder(): array
    {
        try {
            return User::query()->orderBy('id')->pluck('email')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Save a successfully discovered maintainer email.
     */
    public function saveDiscoveredEmail(string $email): void
    {
        try {
            AppSetting::setValue(self::SETTING_KEY, $email);
        } catch (\Throwable) {
            // DB may not be available; silently ignore
        }
    }
}
