<?php

use App\Models\UploadSession;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('clippings:fetch-content')->dailyAt('01:00');

if (class_exists(\Spatie\Browsershot\Browsershot::class)) {
    Schedule::command('clippings:screenshot')->dailyAt('02:00');
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
