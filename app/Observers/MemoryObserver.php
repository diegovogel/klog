<?php

namespace App\Observers;

use App\Models\Memory;
use App\Services\SearchIndexer;

class MemoryObserver
{
    public function __construct(private readonly SearchIndexer $indexer) {}

    public function saved(Memory $memory): void
    {
        $this->indexer->index($memory);
    }

    public function deleted(Memory $memory): void
    {
        // Soft-deleted rows are excluded from search via the Eloquent default
        // scope, so we drop their FTS entry to keep the index lean and avoid
        // leaking data into any future code path that queries FTS directly.
        $this->indexer->remove($memory);
    }

    public function restored(Memory $memory): void
    {
        $this->indexer->index($memory);
    }

    public function forceDeleted(Memory $memory): void
    {
        $this->indexer->remove($memory);
    }
}
