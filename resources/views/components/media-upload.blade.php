@props(['name', 'label', 'max' => 20])

@php($acceptedFormats = \App\Enums\MimeType::labelsByMediaType())

<div class="form-group">
    <label class="form-label">{{ $label }}</label>
    <small class="form-hint">
        Accepted formats:
        @foreach($acceptedFormats as $type => $labels)
            <strong>{{ ucfirst($type) }}:</strong> {{ implode(', ', $labels) }}@if(! $loop->last) <span aria-hidden="true">·</span> @endif
        @endforeach
    </small>
    <div data-media-upload
         data-max="{{ $max }}"
         data-upload-max-file-size="{{ config('klog.uploads.max_file_size', 500 * 1024 * 1024) }}"
         data-image-max-dimension="{{ config('klog.media_optimization.image_max_dimension', 2048) }}"
         data-image-quality="{{ config('klog.media_optimization.image_quality', 85) }}"
         data-upload-init-url="{{ route('uploads.init') }}"
         data-upload-chunk-url="{{ route('uploads.chunk', ['uploadSession' => '__ID__']) }}"
         data-upload-cancel-url="{{ route('uploads.cancel', ['uploadSession' => '__ID__']) }}"
         class="media-upload">
        <div data-media-upload-preview class="media-upload__preview"></div>

        <label class="media-upload__dropzone" data-media-upload-dropzone>
            <input
                type="file"
                name="{{ $name }}[]"
                multiple
                accept="image/*,video/*,audio/*"
                data-media-upload-input
                hidden
            >
            <span data-media-upload-label class="media-upload__dropzone-text">
                Click or drag files here to add photos, videos, or audio
            </span>
        </label>

        <small data-media-upload-counter class="media-upload__counter" hidden>
            <span data-media-upload-count>0</span> / {{ $max }} files
        </small>

        <x-media-capture />
    </div>
    @error($name)
    <p class="form-error">{{ $message }}</p>
    @enderror
    @error($name . '.*')
    <p class="form-error">{{ $message }}</p>
    @enderror
</div>
