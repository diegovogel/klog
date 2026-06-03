<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
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

        // Serialize all invocations (daily schedule, deploy hook, manual runs)
        // so two resets can't run migrate:fresh against the same SQLite file and
        // uploads dir at once. Use the file store: the database cache table is
        // wiped by migrate:fresh mid-command, which would drop a DB-backed lock.
        $lock = Cache::store('file')->lock('demo-reset', 600);

        if (! $lock->get()) {
            $this->warn('Another demo:reset is already running; skipping.');

            return self::SUCCESS;
        }

        try {
            return $this->rebuild();
        } finally {
            $lock->release();
        }
    }

    /**
     * Take the site offline, rebuild and reseed, then bring it back — but only
     * on success. On failure the app is left in maintenance mode rather than
     * served with an empty/half-seeded database (which would also crash /login,
     * since it now queries the users table to decide whether to show the demo
     * panel). Each step's non-zero exit aborts the rest.
     */
    private function rebuild(): int
    {
        $steps = [
            ['Clearing demo media…', function (): bool {
                // Best-effort: a fresh instance has no uploads dir yet.
                Storage::disk('local')->deleteDirectory('uploads');

                return true;
            }],
            ['Rebuilding database…', fn (): bool => $this->call('migrate:fresh', ['--force' => true]) === self::SUCCESS],
            ['Evicting stale sessions…', function (): bool {
                // The demo runs on the `database` session driver (see .env), which
                // migrate:fresh already empties; the `file` driver keeps sessions
                // on disk, so sweep those too. Both cover every way the demo is
                // actually deployed. Other persistent drivers (redis, memcached,
                // dynamodb) would need their own flush, and `cookie` sessions live
                // client-side and can't be evicted server-side at all — none are
                // used here, so we don't carry per-driver flush logic for them.
                if (config('session.driver') === 'file') {
                    File::cleanDirectory(config('session.files'));
                }

                return true;
            }],
            ['Seeding demo content…', fn (): bool => $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]) === self::SUCCESS],
            ['Rebuilding search index…', fn (): bool => $this->call('search:reindex') === self::SUCCESS],
        ];

        $this->call('down', ['--retry' => 5]);

        foreach ($steps as [$message, $step]) {
            $this->info($message);

            if (! $step()) {
                $this->error("demo:reset aborted at: {$message}. Leaving the app in maintenance mode.");

                return self::FAILURE;
            }
        }

        $this->call('up');
        $this->info('Demo reset complete.');

        return self::SUCCESS;
    }
}
