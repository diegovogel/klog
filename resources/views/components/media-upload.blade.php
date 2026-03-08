@props(['name', 'label', 'max' => 20])

<div class="form-group">
    <label class="form-label">{{ $label }}</label>
    <div data-media-upload data-max="{{ $max }}" class="media-upload">
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
