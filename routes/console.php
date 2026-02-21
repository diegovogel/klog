<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('clippings:fetch-content')->dailyAt('01:00');

if (class_exists(\Spatie\Browsershot\Browsershot::class)) {
    Schedule::command('clippings:screenshot')->dailyAt('02:00');
}
