<?php

namespace App\Services;

use App\Models\AppSetting;

class ScreenshotFeatureService
{
    public const ENABLED_KEY = 'screenshots_enabled';

    public const STATUS_CACHE_KEY = 'screenshots.install.status';

    public const STUCK_QUEUED_AFTER_SECONDS = 60;

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
            'state_changed_at' => time(),
        ], now()->addHour());
    }

    /**
     * Reserve the install/uninstall slot. Returns true if this caller now
     * owns the slot; false if another operation is already queued or running.
     * Terminal states (success / failed / idle) are overwritten so admins
     * can re-trigger after a finished run without waiting for the cache TTL.
     *
     * Atomicity comes from a short-lived lock around the read+write so two
     * concurrent admins can't both see an idle state and both reserve.
     */
    public function tryReserve(string $action): bool
    {
        $lock = cache()->lock(self::STATUS_CACHE_KEY.'.lock', 5);

        if (! $lock->get()) {
            return false;
        }

        try {
            $current = cache()->get(self::STATUS_CACHE_KEY);

            if (is_array($current) && in_array($current['state'] ?? '', ['queued', 'running'], true)) {
                // If the slot has been stuck in `queued` for too long (worker
                // never picked it up, queue is down, etc.), allow re-reservation
                // so admins aren't locked out for the full cache TTL.
                if ($this->isStuckInQueued($current)) {
                    // fall through and re-reserve
                } else {
                    return false;
                }
            }

            $this->markStatus('queued', 'Waiting for worker…', $action);

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function isStuckInQueued(array $status): bool
    {
        if (($status['state'] ?? null) !== 'queued') {
            return false;
        }

        $changedAt = $status['state_changed_at'] ?? null;

        return is_int($changedAt)
            && (time() - $changedAt) > self::STUCK_QUEUED_AFTER_SECONDS;
    }

    public function clearStatus(): void
    {
        cache()->forget(self::STATUS_CACHE_KEY);
    }
}
