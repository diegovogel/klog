@props(['tags', 'selected' => []])

<div class="form-group">
    <label for="tag-input" class="form-label">Tags</label>
    <div data-tag-input class="tag-input">
        <input
            id="tag-input"
            type="text"
            list="tag-suggestions"
            class="form-input"
            placeholder="Add a tag…"
            maxlength="100"
            autocomplete="off"
            data-tag-input-field
        >
        <datalist id="tag-suggestions">
            @foreach($tags as $tag)
                <option value="{{ $tag->name }}" data-tag-id="{{ $tag->id }}">
            @endforeach
        </datalist>

        <div data-tag-input-pills class="tag-input__pills">
            @foreach(old('tags', $selected) as $tagId)
                @php $tag = $tags->firstWhere('id', $tagId) @endphp
                @if($tag)
                    <span class="tag-input__pill">
                        {{ $tag->name }}
                        <input type="hidden" name="tags[]" value="{{ $tag->id }}">
                        <button type="button" class="tag-input__remove" aria-label="Remove {{ $tag->name }}">&times;</button>
                    </span>
                @endif
            @endforeach
            @foreach(old('new_tags', []) as $newTag)
                <span class="tag-input__pill">
                    {{ $newTag }}
                    <input type="hidden" name="new_tags[]" value="{{ $newTag }}">
                    <button type="button" class="tag-input__remove" aria-label="Remove {{ $newTag }}">&times;</button>
                </span>
            @endforeach
        </div>
    </div>
    @error('tags')
    <p class="form-error">{{ $message }}</p>
    @enderror
    @error('tags.*')
    <p class="form-error">{{ $message }}</p>
    @enderror
    @error('new_tags.*')
    <p class="form-error">{{ $message }}</p>
    @enderror
</div>
