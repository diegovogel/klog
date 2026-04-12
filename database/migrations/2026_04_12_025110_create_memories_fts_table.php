<?php

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
        DB::table('memories')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk(200, function ($memories): void {
                foreach ($memories as $memory) {
                    $tagNames = DB::table('tags')
                        ->join('memory_tag', 'tags.id', '=', 'memory_tag.tag_id')
                        ->where('memory_tag.memory_id', $memory->id)
                        ->whereNull('tags.deleted_at')
                        ->pluck('tags.name')
                        ->implode(' ');

                    $clippingUrls = DB::table('web_clippings')
                        ->where('memory_id', $memory->id)
                        ->whereNull('deleted_at')
                        ->pluck('url')
                        ->implode(' ');

                    DB::table('memories_fts')->insert([
                        'rowid' => $memory->id,
                        'title' => (string) ($memory->title ?? ''),
                        'content' => strip_tags((string) ($memory->content ?? '')),
                        'tag_names' => $tagNames,
                        'clipping_urls' => $clippingUrls,
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS memories_fts');
    }
};
