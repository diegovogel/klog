<?php

namespace App\Services;

use App\Models\Memory;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the `memories_fts` virtual table that backs search.
 *
 * Every mutation that changes a memory's searchable content — its title,
 * body text, tag list, or web clipping URLs — should flow through this
 * service so the index stays consistent with the source of truth.
 */
class SearchIndexer
{
    /**
     * Insert or update the FTS row for a single memory.
     *
     * Always re-loads tags and webClippings — callers typically reach
     * this method right after attaching relationships through the pivot,
     * and any in-memory Collection cached on the model would be stale.
     */
    public function index(Memory $memory): void
    {
        $memory->load(['tags', 'webClippings']);

        $row = [
            'title' => (string) ($memory->title ?? ''),
            'content' => self::extractText((string) ($memory->content ?? '')),
            'tag_names' => $memory->tags->pluck('name')->implode(' '),
            'clipping_urls' => $memory->webClippings->pluck('url')->implode(' '),
        ];

        // DELETE+INSERT is wrapped so concurrent writers can't see a
        // half-removed row.
        DB::transaction(function () use ($memory, $row): void {
            DB::table('memories_fts')->where('rowid', $memory->id)->delete();
            DB::table('memories_fts')->insert(array_merge(['rowid' => $memory->id], $row));
        });
    }

    /**
     * Remove the FTS row for a memory. Safe to call even if the row
     * isn't indexed.
     */
    public function remove(Memory $memory): void
    {
        DB::table('memories_fts')->where('rowid', $memory->id)->delete();
    }

    /**
     * Strip HTML from indexed content while preserving word boundaries.
     *
     * A naive `strip_tags($html)` collapses `hello<br>world` into
     * `helloworld`, which poisons FTS5 tokenization — the two words get
     * indexed as one, and searches for either miss. Replace every tag
     * with a space before stripping, then collapse runs of whitespace.
     */
    public static function extractText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $spaced = preg_replace('/<[^>]*>/', ' ', $html) ?? $html;
        $decoded = html_entity_decode($spaced, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/', ' ', $decoded));
    }

    /**
     * Wipe the index and rebuild it from scratch. Used by search:reindex
     * and by install/recovery workflows.
     */
    public function rebuild(): int
    {
        DB::table('memories_fts')->delete();

        $count = 0;
        Memory::query()
            ->with(['tags', 'webClippings'])
            ->orderBy('id')
            ->chunk(200, function ($memories) use (&$count): void {
                foreach ($memories as $memory) {
                    $this->index($memory);
                    $count++;
                }
            });

        return $count;
    }
}
