/**
 * Tag input: text field with datalist autocomplete and pill UI.
 *
 * Usage: Add data-tag-input to a wrapper element containing:
 *   - [data-tag-input-field] the text input
 *   - [data-tag-input-pills] the container for tag pills
 *   - a <datalist> with <option> elements (option value = tag name, data-tag-id = tag ID)
 */
document.querySelectorAll('[data-tag-input]').forEach(container => {
    const field = container.querySelector('[data-tag-input-field]')
    const pills = container.querySelector('[data-tag-input-pills]')
    const datalist = container.querySelector('datalist')
    if (!field || !pills) return

    const datalistId = field.getAttribute('list')

    // Only show datalist suggestions after 2+ characters
    if (datalistId) {
        field.removeAttribute('list')

        let prevLength = 0

        field.addEventListener('input', () => {
            const len = field.value.trim().length

            if (len >= 2) {
                field.setAttribute('list', datalistId)
            } else {
                field.removeAttribute('list')
            }

            // Detect datalist selection: value jumped by more than 1 character
            // and now exactly matches a datalist option
            if (len > prevLength + 1 && datalist) {
                const match = Array.from(datalist.options).find(
                    opt => opt.value === field.value
                )
                if (match) {
                    addTag(field.value)
                    prevLength = 0
                    return
                }
            }

            prevLength = len
        })
    }

    field.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault()
            addTag(field.value)
        }
    })

    pills.addEventListener('click', e => {
        const removeBtn = e.target.closest('.tag-input__remove')
        if (removeBtn) {
            removeBtn.closest('.tag-input__pill').remove()
        }
    })

    function addTag (raw) {
        const name = raw.trim()
        if (!name) return

        // Check for duplicates among existing pills (case-insensitive)
        const existing = pills.querySelectorAll('.tag-input__pill')
        for (const pill of existing) {
            const pillName = pill.firstChild.textContent.trim()
            if (pillName.toLowerCase() === name.toLowerCase()) {
                field.value = ''
                field.removeAttribute('list')
                return
            }
        }

        // Check if this matches an existing tag in the datalist
        let tagId = null
        if (datalist) {
            const option = Array.from(datalist.options).find(
                opt => opt.value.toLowerCase() === name.toLowerCase()
            )
            if (option) {
                tagId = option.dataset.tagId
            }
        }

        const pill = document.createElement('span')
        pill.className = 'tag-input__pill'

        const hidden = document.createElement('input')
        hidden.type = 'hidden'
        if (tagId) {
            hidden.name = 'tags[]'
            hidden.value = tagId
        } else {
            hidden.name = 'new_tags[]'
            hidden.value = name
        }

        const removeBtn = document.createElement('button')
        removeBtn.type = 'button'
        removeBtn.className = 'tag-input__remove'
        removeBtn.setAttribute('aria-label', 'Remove ' + name)
        removeBtn.textContent = '\u00d7'

        pill.appendChild(document.createTextNode(name))
        pill.appendChild(hidden)
        pill.appendChild(removeBtn)
        pills.appendChild(pill)

        field.value = ''
        field.removeAttribute('list')
    }
})
