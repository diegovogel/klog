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

        $this->info('Clearing demo media…');
        Storage::disk('local')->deleteDirectory('uploads');

        // Bail on the first failing step so a partial/empty database is never
        // reported as a healthy reset (the scheduler keys off this exit code).
        $steps = [
            ['Rebuilding database…', 'migrate:fresh', ['--force' => true]],
            ['Seeding demo content…', 'db:seed', ['--class' => DemoSeeder::class, '--force' => true]],
            ['Rebuilding search index…', 'search:reindex', []],
        ];

        foreach ($steps as [$message, $command, $arguments]) {
            $this->info($message);

            if ($this->call($command, $arguments) !== self::SUCCESS) {
                $this->error("demo:reset aborted: '{$command}' failed.");

                return self::FAILURE;
            }
        }

        $this->info('Demo reset complete.');

        return self::SUCCESS;
    }
}
