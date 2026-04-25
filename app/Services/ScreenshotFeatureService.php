<?php

namespace App\Services;

use App\Models\AppSetting;

class ScreenshotFeatureService
{
    public const ENABLED_KEY = 'screenshots_enabled';

    public const STATUS_CACHE_KEY = 'screenshots.install.status';

    public function isInstalled(): bool
    {
        return app(ScreenshotService::class)->isAvailable();
    }

    public function isEnabled(): bool
    {
        try {
            $stored = AppSetting::getValue(self::ENABLED_KEY);
            if ($stored === null) {
                return true;
            }

            return $stored === 'true' || $stored === '1';
        } catch (\Throwable) {
            return true;
        }
    }

    public function setEnabled(bool $enabled): void
    {
        AppSetting::setValue(self::ENABLED_KEY, $enabled ? 'true' : 'false');
    }

    /**
     * @return array{state: string, message: ?string, action: ?string}
     */
    public function status(): array
    {
        $data = cache()->get(self::STATUS_CACHE_KEY);

        if (! is_array($data)) {
            return ['state' => 'idle', 'message' => null, 'action' => null];
        }

        return $data;
    }

    public function markStatus(string $state, ?string $message = null, ?string $action = null): void
    {
        cache()->put(self::STATUS_CACHE_KEY, [
            'state' => $state,
            'message' => $message,
            'action' => $action,
        ], now()->addHour());
    }

    /**
     * Reserve the install/uninstall slot. Returns true if this caller now
     * owns the slot; false if another operation is already queued or running.
     * Terminal states (success / failed / idle) are overwritten so admins
     * can re-trigger after a finished run without waiting for the cache TTL.
     */
    public function tryReserve(string $action): bool
    {
        $current = cache()->get(self::STATUS_CACHE_KEY);

        if (is_array($current) && in_array($current['state'] ?? '', ['queued', 'running'], true)) {
            return false;
        }

        $this->markStatus('queued', 'Waiting for worker…', $action);

        return true;
    }

    public function clearStatus(): void
    {
        cache()->forget(self::STATUS_CACHE_KEY);
    }
}
