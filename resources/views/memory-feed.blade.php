<x-layouts.app title="Feed – {{ config('app.name', 'Klog') }}"
               page-title="Feed">
    <a href="{{route('memories.create')}}">New Memory</a>

    @foreach($memories as $memory)
        <x-memory-card :memory="$memory"/>
    @endforeach

    {{ $memories->links() }}
</x-layouts.app>
