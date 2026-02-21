/**
 * Minimal rich text editor using contenteditable.
 * Supports bold (<strong>), italic (<em>), and links (<a>).
 *
 * Usage: Add data-rich-editor to a wrapper element containing:
 *   - [data-editor-toolbar] with buttons: [data-command="bold|italic|link"]
 *   - [data-editor-content] (the contenteditable area)
 *   - a hidden <textarea name="content"> to sync HTML into
 */
document.querySelectorAll('[data-rich-editor]').forEach(editor => {
    const toolbar = editor.querySelector('[data-editor-toolbar]')
    const content = editor.querySelector('[data-editor-content]')
    const textarea = editor.querySelector('textarea[name="content"]')

    if (!toolbar || !content || !textarea) return

    // Populate contenteditable from textarea (for old() repopulation)
    if (textarea.value.trim()) {
        content.innerHTML = textarea.value
    }

    // Toolbar button actions
    toolbar.addEventListener('click', e => {
        const button = e.target.closest('[data-command]')
        if (!button) return

        e.preventDefault()
        content.focus()

        const command = button.dataset.command

        if (command === 'link') {
            handleLink(content)
        } else {
            document.execCommand(command)
        }

        updateToolbarState()
    })

    // Update active state on selection change
    content.addEventListener('keyup', updateToolbarState)
    content.addEventListener('mouseup', updateToolbarState)

    // Keyboard shortcuts
    content.addEventListener('keydown', e => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault()
            handleLink(content)
            updateToolbarState()
        }
    })

    // Sync contenteditable HTML into the hidden textarea on form submit
    const form = editor.closest('form')
    if (form) {
        form.addEventListener('submit', () => {
            textarea.value = sanitize(content.innerHTML)
        })
    }

    function updateToolbarState () {
        toolbar.querySelectorAll('[data-command]').forEach(button => {
            const command = button.dataset.command
            let active

            if (command === 'link') {
                const selection = window.getSelection()
                const anchor = selection.anchorNode?.parentElement?.closest('a')
                active = !!anchor
            } else {
                active = document.queryCommandState(command)
            }

            button.classList.toggle('is-active', active)
            button.setAttribute('aria-pressed', String(active))
        })
    }

    function handleLink (editable) {
        const selection = window.getSelection()
        if (!selection.rangeCount) return

        const range = selection.getRangeAt(0)
        const anchor = selection.anchorNode?.parentElement?.closest('a')

        if (anchor && editable.contains(anchor)) {
            // Remove existing link — unwrap the anchor
            const parent = anchor.parentNode
            while (anchor.firstChild) {
                parent.insertBefore(anchor.firstChild, anchor)
            }
            parent.removeChild(anchor)
            return
        }

        const url = prompt('URL:')
        if (!url) return

        if (range.collapsed) {
            // No selection — insert a new link with the URL as text
            const link = document.createElement('a')
            link.href = url
            link.textContent = url
            link.target = '_blank'
            link.rel = 'noopener noreferrer'
            range.insertNode(link)

            // Move cursor after the link
            range.setStartAfter(link)
            range.collapse(true)
            selection.removeAllRanges()
            selection.addRange(range)
        } else {
            // Wrap the selection in a link
            const link = document.createElement('a')
            link.href = url
            link.target = '_blank'
            link.rel = 'noopener noreferrer'
            range.surroundContents(link)
        }
    }

    /**
     * Normalize browser output to semantic HTML.
     * Replaces <b> with <strong> and <i> with <em>.
     * Strips empty content down to an empty string.
     */
    function sanitize (html) {
        let cleaned = html
            .replace(/<b(\s|>)/gi, '<strong$1')
            .replace(/<\/b>/gi, '</strong>')
            .replace(/<i(\s|>)/gi, '<em$1')
            .replace(/<\/i>/gi, '</em>')

        // If content is just empty tags / whitespace / <br>, return empty string
        const text = cleaned.replace(/<[^>]*>/g, '').trim()
        if (!text) return ''

        return cleaned
    }
})
