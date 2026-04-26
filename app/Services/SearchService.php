<?php

namespace App\Services;

use App\Models\Memory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Executes search queries against the FTS5 virtual table and applies
 * structured filters via Eloquent scopes on Memory.
 *
 * Query strategy:
 *   - If the user provided text, JOIN memories_fts on memories.id and order
 *     by FTS5's BM25-style `rank` column.
 *   - Otherwise (filters-only or empty state), skip FTS entirely and order
 *     by memory_date DESC so the result reads like the feed.
 *
 * All filters run as Eloquent scopes so they apply identically in both
 * paths.
 */
class SearchService
{
    /**
     * @param  array{types?: array<int, string>, from?: \Carbon\CarbonInterface|null, to?: \Carbon\CarbonInterface|null, children?: array<int, int>, user_id?: int|null}  $filters
     */
    public function search(string $query, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $match = $this->buildMatchQuery($query);

        $builder = Memory::query()->with(['media', 'children', 'tags', 'webClippings', 'user']);

        if ($match !== '') {
            $builder
                ->join('memories_fts', 'memories_fts.rowid', '=', 'memories.id')
                ->whereRaw('memories_fts MATCH ?', [$match])
                ->orderByRaw('memories_fts.rank');
        } else {
            $builder->orderByDesc('memory_date');
        }

        $builder
            ->filterByTypes($filters['types'] ?? [])
            ->filterByDateRange($filters['from'] ?? null, $filters['to'] ?? null)
            ->filterByChildren($filters['children'] ?? [])
            ->filterByUser($filters['user_id'] ?? null);

        return $builder
            ->select('memories.*')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Normalize a user's free-text query into a safe FTS5 MATCH expression.
     *
     * Strips FTS5-significant characters (quotes, parens, colons) so input
     * can't escape into operator syntax, then appends a prefix marker to
     * each token so typing "birth" matches "birthday". Tokens are space-
     * separated, which FTS5 interprets as an implicit AND.
     *
     * Returns an empty string when the query has no usable tokens —
     * callers treat that the same as "no query".
     */
    public function buildMatchQuery(string $query): string
    {
        // Replace FTS5 operator characters (`"():*+-^` etc.) with spaces
        // so they can't escape into operator syntax.
        $clean = preg_replace('/[^\p{L}\p{N}\s_]+/u', ' ', $query) ?? '';

        $tokens = preg_split('/\s+/', trim($clean), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tokens === []) {
            return '';
        }

        return collect($tokens)
            // Lowercase each token so FTS5 doesn't interpret all-caps words
            // like AND / OR / NOT as reserved operators (which would emit a
            // syntax error on `AND*`). Porter is case-insensitive so this
            // changes nothing about what actually matches.
            ->map(fn (string $token): string => mb_strtolower($token).'*')
            ->implode(' ');
    }
}
