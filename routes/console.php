<?php

use App\Models\UploadSession;
use App\Services\ScreenshotFeatureService;
use App\Services\TwoFactorService;
use App\Services\UserInviteService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('clippings:fetch-content')->dailyAt('01:00');

if (class_exists(\Spatie\Browsershot\Browsershot::class)) {
    // Use Schedule::command so the scheduler gets the artisan exit code —
    // Schedule::call wrapping Artisan::call would always record success.
    Schedule::command('clippings:screenshot')
        ->dailyAt('02:00')
        ->when(fn () => app(ScreenshotFeatureService::class)->isEnabled()
            && app(ScreenshotFeatureService::class)->isInstalled());
}

// Clean up orphaned and expired upload sessions
Schedule::call(function () {
    $ttlHours = config('klog.uploads.session_ttl', 24);

    // Delete incomplete sessions older than TTL
    UploadSession::where('created_at', '<', now()->subHours($ttlHours))
        ->whereNull('completed_at')
        ->each(function (UploadSession $session) {
            Storage::disk('local')->deleteDirectory($session->chunksDirectory());
            $session->delete();
        });

    // Delete completed sessions older than 7 days (media records already created)
    UploadSession::whereNotNull('completed_at')
        ->where('completed_at', '<', now()->subWeek())
        ->delete();
})->dailyAt('03:00');

Schedule::call(function () {
    app(TwoFactorService::class)->pruneExpiredRememberedDevices();
})->dailyAt('03:30');

Schedule::call(function () {
    app(UserInviteService::class)->purgeExpired();
})->dailyAt('04:00');
