<x-layouts.app title="New Memory – {{ config('app.name', 'Klog') }}">

    <h1 class="page-title">New Memory</h1>

    <form method="POST"
          action="{{ route('memories.store') }}"
          enctype="multipart/form-data"
          class="memory-form">
        @csrf

        @if ($errors->any())
            <div class="alert alert--error" role="alert">
                There were problems with some of the memory info. Please see below.
            </div>
        @endif

        <div class="form-group">
            <label for="title" class="form-label">Title</label>
            <input
                id="title"
                name="title"
                type="text"
                class="form-input"
                value="{{ old('title') }}"
                autofocus
            >
            @error('title')
            <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-group">
            <label for="memory_date" class="form-label">Date</label>
            <input
                id="memory_date"
                name="memory_date"
                type="date"
                class="form-input"
                value="{{ old('memory_date', now()->format('Y-m-d')) }}"
                @if($latestMemoryDate) data-latest-memory-date="{{ \Illuminate\Support\Carbon::parse($latestMemoryDate)->format('Y-m-d') }}" @endif
            >
            <small id="memory-date-warning" class="form-hint" hidden>
                Heads up! There are memories after this date, so this memory won't be at the top of the feed.
            </small>
            @error('memory_date')
            <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <x-rich-editor name="content" label="Content" :value="old('content')" />

        <x-media-upload name="media" label="Media" />

        <x-web-clippings />

        <x-child-selector :children="$children" />

        <x-tag-input :tags="$tags" />

        <div class="memory-form__submit">
            <button type="submit" class="btn btn--primary btn--block">Save Memory</button>
        </div>
    </form>

</x-layouts.app>
