<?php

use App\Services\SearchIndexer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Virtual FTS5 table. Uses the porter tokenizer for stemming
        // (dish ↔ dishes) combined with unicode61 for diacritic-insensitive
        // matching (café ↔ cafe). Each searchable field is a separate column
        // so callers can target any one of them; MATCH by default searches all.
        //
        // rowid is implicitly aliased to memories.id — the virtual table is
        // populated with explicit rowids that mirror the source table so we
        // can JOIN back to memories in a single query.
        DB::statement(<<<'SQL'
            CREATE VIRTUAL TABLE memories_fts USING fts5(
                title,
                content,
                tag_names,
                clipping_urls,
                tokenize = 'porter unicode61 remove_diacritics 2'
            )
        SQL);

        // Backfill existing rows so upgraders don't have to run search:reindex.
        // Tags and clipping URLs are fetched once per chunk via whereIn
        // instead of once per memory — the per-row approach was 2×N queries,
        // which becomes pain on any reasonably populated database.
        DB::table('memories')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk(200, function ($memories): void {
                $memoryIds = $memories->pluck('id')->all();

                $tagsByMemory = DB::table('tags')
                    ->join('memory_tag', 'tags.id', '=', 'memory_tag.tag_id')
                    ->whereIn('memory_tag.memory_id', $memoryIds)
                    ->whereNull('tags.deleted_at')
                    ->select('memory_tag.memory_id', 'tags.name')
                    ->get()
                    ->groupBy('memory_id')
                    ->map(fn ($rows) => $rows->pluck('name')->implode(' '));

                $clippingsByMemory = DB::table('web_clippings')
                    ->whereIn('memory_id', $memoryIds)
                    ->whereNull('deleted_at')
                    ->select('memory_id', 'url')
                    ->get()
                    ->groupBy('memory_id')
                    ->map(fn ($rows) => $rows->pluck('url')->implode(' '));

                foreach ($memories as $memory) {
                    DB::table('memories_fts')->insert([
                        'rowid' => $memory->id,
                        'title' => (string) ($memory->title ?? ''),
                        'content' => SearchIndexer::extractText((string) ($memory->content ?? '')),
                        'tag_names' => (string) ($tagsByMemory->get($memory->id) ?? ''),
                        'clipping_urls' => (string) ($clippingsByMemory->get($memory->id) ?? ''),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS memories_fts');
    }
};
