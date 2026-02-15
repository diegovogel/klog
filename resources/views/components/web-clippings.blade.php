@props(['name' => 'clippings'])

<div>
    <label>Web Clippings</label>
    <div data-web-clippings class="web-clippings">
        <div data-web-clippings-list class="web-clippings__list"></div>

        <button type="button" data-web-clippings-add class="web-clippings__add secondary">
            + Add Clipping
        </button>
    </div>
    @error($name)
    <p>{{ $message }}</p>
    @enderror
    @error($name . '.*')
    <p>{{ $message }}</p>
    @enderror
</div>
