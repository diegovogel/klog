<?php

namespace App\Console\Commands;

use App\Services\SearchIndexer;
use Illuminate\Console\Command;

class ReindexSearch extends Command
{
    protected $signature = 'search:reindex';

    protected $description = 'Rebuild the memories_fts search index from scratch';

    public function handle(SearchIndexer $indexer): int
    {
        $this->info('Rebuilding search index...');

        $count = $indexer->rebuild();

        $this->info("Indexed {$count} memories.");

        return self::SUCCESS;
    }
}
