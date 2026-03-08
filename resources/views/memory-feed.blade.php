<x-layouts.app title="Feed – {{ config('app.name', 'Klog') }}">

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
