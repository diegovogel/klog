<x-layouts.app title="New Memory – {{ config('app.name', 'Klog') }}"
               pageTitle="New Memory">

    <form method="POST"
          action="{{ route('memories.store') }}"
          enctype="multipart/form-data"
          class="memory-form">
        @csrf

        <div>
            <label for="title">Title</label>
            <input
                id="title"
                name="title"
                type="text"
                value="{{ old('title') }}"
                autofocus
            >
            @error('title')
            <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="memory_date">Date</label>
            <input
                id="memory_date"
                name="memory_date"
                type="date"
                value="{{ old('memory_date', now()->format('Y-m-d')) }}"
                @if($latestMemoryDate) data-latest-memory-date="{{ \Illuminate\Support\Carbon::parse($latestMemoryDate)->format('Y-m-d') }}" @endif
            >
            <small id="memory-date-warning" hidden>
                Heads up! There are memories after this date, so this memory won't be at the top of the feed.
            </small>
            @error('memory_date')
            <p>{{ $message }}</p>
            @enderror
        </div>

        <x-rich-editor name="content" label="Content" :value="old('content')" />

        <x-media-upload name="media" label="Media" />

        <button type="submit">Save Memory
        </button>
    </form>

</x-layouts.app>
