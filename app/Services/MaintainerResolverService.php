<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;

class MaintainerResolverService
{
    /** Admin-configured value via the Settings UI. */
    private const SETTING_KEY = 'maintainer_email';

    /** Cached recipient discovered via the user-iteration fallback. Lower priority than the env var. */
    private const AUTODISCOVERY_KEY = 'maintainer_email_autodiscovered';

    /**
     * Resolve the maintainer email address.
     *
     * Priority:
     * 1. Admin-configured `maintainer_email` (Settings UI)
     * 2. `MAINTAINER_EMAIL` env var
     * 3. Auto-discovered fallback (cached from a prior successful send)
     * 4. null (caller iterates users)
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

        try {
            $autodiscovered = AppSetting::getValue(self::AUTODISCOVERY_KEY);
            if ($autodiscovered !== null && $autodiscovered !== '') {
                return $autodiscovered;
            }
        } catch (\Throwable) {
            // DB unavailable — keep falling through.
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
            return User::query()
                ->active()
                ->whereDoesntHave('invite', fn ($q) => $q->whereNull('accepted_at'))
                ->orderBy('id')
                ->pluck('email')
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Save a successfully discovered maintainer email under a key that has
     * lower precedence than both the admin-configured value and the env var,
     * so a transient send failure to the configured address can't permanently
     * reroute future mail.
     */
    public function saveDiscoveredEmail(string $email): void
    {
        try {
            AppSetting::setValue(self::AUTODISCOVERY_KEY, $email);
        } catch (\Throwable) {
            // DB may not be available; silently ignore
        }
    }
}
