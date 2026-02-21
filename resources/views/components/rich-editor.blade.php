@props(['name', 'label', 'value' => ''])

<div>
    <label for="{{ $name }}">{{ $label }}</label>
    <div data-rich-editor class="rich-editor">
        <div data-editor-toolbar class="rich-editor__toolbar" role="toolbar" aria-label="Formatting">
            <button type="button" data-command="bold" aria-pressed="false" aria-label="Bold" title="Bold (Ctrl+B)"><strong>B</strong></button>
            <button type="button" data-command="italic" aria-pressed="false" aria-label="Italic" title="Italic (Ctrl+I)"><em>I</em></button>
            <button type="button" data-command="link" aria-pressed="false" aria-label="Link" title="Link (Ctrl+K)">🔗</button>
        </div>
        <div
            data-editor-content
            class="rich-editor__content"
            contenteditable="true"
            role="textbox"
            aria-multiline="true"
            aria-label="{{ $label }}"
            id="{{ $name }}"
        ></div>
        <textarea name="{{ $name }}" hidden>{{ $value }}</textarea>
    </div>
    @error($name)
    <p>{{ $message }}</p>
    @enderror
</div>
