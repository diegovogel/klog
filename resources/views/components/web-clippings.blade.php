@props(['name' => 'clippings'])

<div class="form-group">
    <label class="form-label">Web Clippings</label>
    <div data-web-clippings class="web-clippings">
        <div data-web-clippings-list class="web-clippings__list"></div>

        <button type="button" data-web-clippings-add class="web-clippings__add">
            + Add Clipping
        </button>
    </div>
    @error($name)
    <p class="form-error">{{ $message }}</p>
    @enderror
    @error($name . '.*')
    <p class="form-error">{{ $message }}</p>
    @enderror
</div>
