<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DemoReset extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Wipe and re-seed the public demo database back to its seeded state';

    /**
     * Reset the demo to a clean, seeded state.
     *
     * Refuses to run unless the app is in demo mode — this drops every table,
     * so the guard is the only thing standing between it and a production
     * database. Both the schedule and any manual run go through this check.
     */
    public function handle(): int
    {
        if (! config('klog.is_demo')) {
            $this->error('demo:reset is only available when IS_DEMO=true. Aborting.');

            return self::FAILURE;
        }

        // Bail on the first failing step so a partial/empty database is never
        // reported as a healthy reset (the scheduler keys off this exit code).
        $steps = [
            ['Clearing demo media…', function (): bool {
                // Best-effort: a fresh instance has no uploads dir yet.
                Storage::disk('local')->deleteDirectory('uploads');

                return true;
            }],
            ['Rebuilding database…', fn (): bool => $this->call('migrate:fresh', ['--force' => true]) === self::SUCCESS],
            ['Seeding demo content…', fn (): bool => $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]) === self::SUCCESS],
            ['Rebuilding search index…', fn (): bool => $this->call('search:reindex') === self::SUCCESS],
        ];

        // migrate:fresh drops every table while the HTTP app stays live, so take
        // the demo offline for the few seconds the rebuild takes. Without this,
        // concurrent visitors hit "no such table" errors mid-reset every day.
        $this->call('down', ['--retry' => 5]);

        try {
            foreach ($steps as [$message, $step]) {
                $this->info($message);

                if (! $step()) {
                    $this->error("demo:reset aborted at: {$message}");

                    return self::FAILURE;
                }
            }
        } finally {
            $this->call('up');
        }

        $this->info('Demo reset complete.');

        return self::SUCCESS;
    }
}
