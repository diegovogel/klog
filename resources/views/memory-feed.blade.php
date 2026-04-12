<x-layouts.app title="Feed – {{ config('app.name', 'Klog') }}">

    <div class="feed__search">
        <form method="GET" action="{{ route('search') }}" class="feed__search-form" role="search">
            <input
                type="search"
                name="q"
                class="feed__search-input"
                placeholder="Search memories…"
                aria-label="Search memories"
                autocomplete="off"
            >
            <button type="submit">Search</button>
        </form>
    </div>

    @if($memories->isEmpty())
        <div class="feed__empty">
            <h2 class="feed__empty-title">No memories yet</h2>
            <p>Start capturing moments by creating your first memory.</p>
        </div>
    @else
        <div class="feed__list">
            @foreach($memories as $memory)
                <x-memory-card :memory="$memory"/>
            @endforeach
        </div>

        <div class="feed__pagination">
            {{ $memories->links() }}
        </div>
    @endif

</x-layouts.app>
