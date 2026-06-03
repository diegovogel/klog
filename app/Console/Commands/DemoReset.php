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

        $this->info('Rebuilding database…');
        $this->call('migrate:fresh', ['--force' => true]);

        $this->info('Seeding demo content…');
        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->info('Rebuilding search index…');
        $this->call('search:reindex');

        $this->info('Demo reset complete.');

        return self::SUCCESS;
    }
}
