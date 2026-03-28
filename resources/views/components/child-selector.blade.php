@props(['children', 'selected' => []])

<div class="form-group">
    <label class="form-label">Children</label>
    <div data-child-selector class="child-selector">
        <div data-child-selector-list class="child-selector__list">
            @foreach($children as $child)
                <label class="child-selector__btn{{ in_array($child->id, old('children', $selected)) ? ' child-selector__btn--active' : '' }}">
                    <input
                        type="checkbox"
                        name="children[]"
                        value="{{ $child->id }}"
                        class="child-selector__checkbox"
                        {{ in_array($child->id, old('children', $selected)) ? 'checked' : '' }}
                    >
                    {{ $child->name }}
                </label>
            @endforeach
            @foreach(old('new_children', []) as $newChild)
                <label class="child-selector__btn child-selector__btn--active">
                    <input
                        type="checkbox"
                        name="new_children[]"
                        value="{{ $newChild }}"
                        class="child-selector__checkbox"
                        checked
                    >
                    {{ $newChild }}
                </label>
            @endforeach
        </div>

        <button type="button" data-child-selector-add class="child-selector__add-btn">+ Add New</button>

        <dialog data-child-selector-dialog class="child-selector__dialog">
            <div class="child-selector__dialog-form">
                <label for="new-child-name" class="form-label">Name</label>
                <input
                    id="new-child-name"
                    type="text"
                    data-child-selector-name
                    class="form-input"
                    maxlength="100"
                >
                <div class="child-selector__dialog-actions">
                    <button type="button" class="btn btn--primary btn--sm" data-child-selector-confirm>Add</button>
                    <button type="button" class="btn btn--sm" data-child-selector-cancel>Cancel</button>
                </div>
            </div>
        </dialog>
    </div>
    @error('children')
    <p class="form-error">{{ $message }}</p>
    @enderror
    @error('children.*')
    <p class="form-error">{{ $message }}</p>
    @enderror
    @error('new_children.*')
    <p class="form-error">{{ $message }}</p>
    @enderror
</div>
