# Search Implementation Plan

> **Status:** implemented. See PR and CLAUDE.md for the authoritative docs. This
> file is kept as a record of the design decisions.

## Overview

Search runs entirely on **SQLite FTS5**. No Meilisearch, no external server,
no Scout. A virtual FTS5 table (`memories_fts`) is kept in sync with the
source `memories` table via a Laravel model observer + an explicit
`reindexSearch()` method for relationship changes the observer can't see.

This choice replaces the original "Laravel Scout + Meilisearch with LIKE
fallback" plan. FTS5 is part of the SQLite build already in the project,
supports porter stemming (dish ↔ dishes), prefix matching (birth* →
birthday), diacritic folding (café ↔ cafe), and BM25 ranking. It has no
external dependencies and travels with the database file — a perfect fit
for the project's longevity principle.

The one thing FTS5 doesn't have is typo tolerance (brithday ≠ birthday).
Prefix + stemming covers most real-world use for a personal memory app
where you wrote the content yourself. If typo tolerance matters later,
Meilisearch can be added as a proper optional add-on without changing
the rest of the architecture.

## Architecture

```
SearchController
    └── SearchService
            ├── JOIN memories_fts ON rowid = memories.id  (when query present)
            │     + MATCH ? + ORDER BY memories_fts.rank
            └── Eloquent scopes on Memory                  (filters)
                  scopeFilterByTypes / scopeFilterByDateRange /
                  scopeFilterByChildren / scopeFilterByUser
```

### Search Scope (what's searchable)

- `memory.title`
- `memory.content` (HTML tags stripped before indexing)
- Tag names (denormalized into the FTS row)
- Web clipping URLs (denormalized into the FTS row)

### Filters

- **Children** — `whereHas('children', ...)`, OR-of-children semantics
- **Date range** — inclusive `memory_date BETWEEN from AND to`
- **Type** — photo, video, audio, webclip, text; OR logic across selections.
  Each type is matched via relationships (`whereHas('media', ...)`,
  `whereHas('webClippings')`, `content IS NOT NULL AND != ''`)
- **Author** — filter by `memories.user_id`. The filter is hidden in the UI
  when there's only one user in the workspace (`User::count() <= 1`)

### Index Maintenance

- **`MemoryObserver`** reindexes on `saved`, `deleted` (removes), `restored`,
  `forceDeleted` (removes). Handles all direct mutations of the memory row.
- **`Memory::reindexSearch()`** is called explicitly from code that touches
  relationships without dirtying the memory row: the `memories.store` route
  (after tags / web clippings / children are attached), and
  `Memory::syncTagNames` / `attachTagNames`.
- **`search:reindex`** Artisan command wipes and rebuilds the index from
  scratch. For recovery and manual sync.

### FTS5 Configuration

```sql
CREATE VIRTUAL TABLE memories_fts USING fts5(
    title,
    content,
    tag_names,
    clipping_urls,
    tokenize = 'porter unicode61 remove_diacritics 2'
)
```

- `porter` — stems English words (dish ↔ dishes, running ↔ run)
- `unicode61` with `remove_diacritics 2` — case-insensitive, diacritic-folding
- `rowid` is an implicit alias — we insert with explicit rowids mirroring
  `memories.id`, which lets the JOIN in `SearchService` stay a single query

### User Query Sanitization

Free-text user input is not safe to pass directly to `MATCH` — FTS5 has its
own operator syntax (quotes, parens, colons, `AND`/`OR`/`NOT`, `*`, `^`,
`NEAR`). `SearchService::buildMatchQuery()` strips everything except letters,
numbers, whitespace, and underscores, then appends `*` to each token so
`birth` matches `birthday`. Space-separated tokens are implicit AND in FTS5.

---

## File Inventory

New files:
- `app/Console/Commands/ReindexSearch.php` — `search:reindex`
- `app/Http/Controllers/SearchController.php`
- `app/Http/Requests/SearchRequest.php`
- `app/Services/SearchService.php`
- `app/Services/SearchIndexer.php`
- `app/Observers/MemoryObserver.php`
- `database/migrations/*_create_memories_fts_table.php`
- `resources/views/search/index.blade.php`
- `resources/css/views/search.css`
- `tests/Feature/SearchServiceTest.php`
- `tests/Feature/SearchControllerTest.php`
- `tests/Feature/SearchIndexerTest.php`
- `tests/Feature/Commands/ReindexSearchCommandTest.php`

Modified files:
- `app/Models/Memory.php` — `ObservedBy(MemoryObserver)`, filter scopes,
  `reindexSearch()`, sync/attachTagNames call reindex
- `routes/web.php` — search route + reindex call at the end of `memories.store`
- `resources/views/memory-feed.blade.php` — search form at the top
- `resources/css/app.css` — include search.css
- `CLAUDE.md` — documents the feature

---

## Resolved Decisions

1. **Child model** — `Child` is `id` + `name` with a many-to-many to
   `Memory`. Filter UI is a list of checkboxes, one per child.
2. **Author filter** — Hidden when the workspace has only one user. Shown
   (as a dropdown) otherwise.
3. **Initial state** — Shows all memories, paginated. Typing a query or
   selecting a filter narrows the list.
4. **No-results state** — Friendly message + "clear filters" link when a
   query or filter is active but zero memories match.
5. **URL structure** — `/search?q=birthday&types[]=photo&from=2024-01-01`.
   Filters are GET params so results are bookmarkable and shareable.
6. **Engine choice** — SQLite FTS5, not Scout/Meilisearch. See Overview.
