<x-layouts.app>
    <x-slot:title>Feed – {{ config('app.name', 'Klog') }}</x-slot:title>

    <x-slot:page-title>Feed</x-slot:page-title>

    @foreach($memories as $memory)
        <h2>{{$memory->title}}</h2>
    @endforeach
</x-layouts.app>
