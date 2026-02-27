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
     * 1. MAINTAINER_EMAIL env var
     * 2. Previously discovered email (stored in app_settings)
     * 3. null (caller must try users in order)
     */
    public function resolve(): ?string
    {
        $envEmail = config('klog.maintainer_email');
        if ($envEmail !== null && $envEmail !== '') {
            return $envEmail;
        }

        try {
            return AppSetting::getValue(self::SETTING_KEY);
        } catch (\Throwable) {
            return null;
        }
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
