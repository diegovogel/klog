<x-layouts.app>
    <x-slot:title>Feed – {{ config('app.name', 'Klog') }}</x-slot:title>

    <x-slot:page-title>Feed</x-slot:page-title>

    @foreach($memories as $memory)
        <x-memory-card :memory="$memory"/>
    @endforeach

    {{ $memories->links() }}
</x-layouts.app>
