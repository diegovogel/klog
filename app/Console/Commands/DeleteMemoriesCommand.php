<?php

namespace App\Console\Commands;

use App\Models\Memory;
use Illuminate\Console\Command;

class DeleteMemoriesCommand extends Command
{
    protected $signature = 'memory:delete
                            {--before= : Delete memories with a date before this value (Y-m-d)}
                            {--force : Permanently delete records and remove files from disk}';

    protected $description = 'Delete memories and their related media and clippings';

    public function handle(): int
    {
        $query = Memory::query();

        if ($before = $this->option('before')) {
            $query->where('memory_date', '<', $before);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No memories found matching the criteria.');

            return self::SUCCESS;
        }

        $action = $this->option('force') ? 'permanently delete' : 'delete';
        $scope = $this->option('before') ? "before {$before}" : 'all';

        if (! $this->option('no-interaction') &&
            ! $this->confirm("This will {$action} {$count} memories ({$scope}) and their media/clippings. Continue?")) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $deleted = 0;

        $query->each(function (Memory $memory) use (&$deleted) {
            if ($this->option('force')) {
                $memory->forceDeleteWithRelations();
            } else {
                $memory->deleteWithRelations();
            }

            $this->line("  Deleted: {$memory->title} ({$memory->memory_date->format('Y-m-d')})");
            $deleted++;
        });

        $this->info("Done. {$deleted} memories deleted.");

        return self::SUCCESS;
    }
}
