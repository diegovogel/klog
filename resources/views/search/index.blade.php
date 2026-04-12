<x-layouts.app title="Search – {{ config('app.name', 'Klog') }}">

    <section class="search">

        <form method="GET" action="{{ route('search') }}" class="search__form">
            <label for="search-q" class="search__label">Search memories</label>
            <div class="search__field-row">
                <input
                    type="search"
                    id="search-q"
                    name="q"
                    value="{{ $query }}"
                    placeholder="Title, content, tags, web clippings…"
                    autocomplete="off"
                    class="search__field"
                />
                <button type="submit" class="search__submit">Search</button>
            </div>

            <details class="search__filters" @if($query !== '' || collect($filters)->filter()->isNotEmpty()) open @endif>
                <summary class="search__filters-summary">Filters</summary>

                <fieldset class="search__filter-group">
                    <legend>Type</legend>
                    @foreach(\App\Enums\MemoryType::cases() as $type)
                        <label class="search__checkbox">
                            <input
                                type="checkbox"
                                name="types[]"
                                value="{{ $type->value }}"
                                @checked(in_array($type->value, $filters['types'] ?? [], true))
                            >
                            {{ ucfirst($type->value) }}
                        </label>
                    @endforeach
                </fieldset>

                <fieldset class="search__filter-group">
                    <legend>Date range</legend>
                    <label class="search__date">
                        From
                        <input type="date" name="from" value="{{ $filters['from']?->format('Y-m-d') }}">
                    </label>
                    <label class="search__date">
                        To
                        <input type="date" name="to" value="{{ $filters['to']?->format('Y-m-d') }}">
                    </label>
                </fieldset>

                @if($children->isNotEmpty())
                    <fieldset class="search__filter-group">
                        <legend>Children</legend>
                        @foreach($children as $child)
                            <label class="search__checkbox">
                                <input
                                    type="checkbox"
                                    name="children[]"
                                    value="{{ $child->id }}"
                                    @checked(in_array($child->id, $filters['children'] ?? [], true))
                                >
                                {{ $child->name }}
                            </label>
                        @endforeach
                    </fieldset>
                @endif

                @if($showAuthorFilter)
                    <fieldset class="search__filter-group">
                        <legend>Author</legend>
                        <label class="search__date">
                            <select name="user_id">
                                <option value="">Anyone</option>
                                @foreach($users as $author)
                                    <option
                                        value="{{ $author->id }}"
                                        @selected($filters['user_id'] === $author->id)
                                    >{{ $author->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </fieldset>
                @endif

                <div class="search__filter-actions">
                    <a href="{{ route('search') }}" class="search__clear">Clear filters</a>
                </div>
            </details>
        </form>

        @if($query !== '' || collect($filters)->filter(fn ($v) => ! blank($v))->isNotEmpty())
            <p class="search__results-summary">
                {{ $results->total() }} {{ \Illuminate\Support\Str::plural('result', $results->total()) }}
                @if($query !== '')
                    for <strong>{{ $query }}</strong>
                @endif
            </p>
        @endif

        @if($results->isEmpty())
            @if($query !== '' || collect($filters)->filter(fn ($v) => ! blank($v))->isNotEmpty())
                <div class="search__empty">
                    <h2 class="search__empty-title">No memories match your search</h2>
                    <p>Try a different query or <a href="{{ route('search') }}">clear your filters</a>.</p>
                </div>
            @else
                <div class="search__empty">
                    <h2 class="search__empty-title">No memories yet</h2>
                    <p>Start capturing moments by creating your first memory.</p>
                </div>
            @endif
        @else
            <div class="search__results">
                @foreach($results as $memory)
                    <x-memory-card :memory="$memory"/>
                @endforeach
            </div>

            <div class="search__pagination">
                {{ $results->links() }}
            </div>
        @endif

    </section>

</x-layouts.app>
