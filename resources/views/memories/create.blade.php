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

        <x-rich-editor name="content" label="Content" :value="old('content')" />

        <x-media-upload name="media" label="Media" />

        <button type="submit">Save Memory
        </button>
    </form>

</x-layouts.app>
