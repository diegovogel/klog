/**
 * Child selector: checkbox-button UI with "Add New" dialog and no-children warning.
 *
 * Usage: Add data-child-selector to a wrapper element containing:
 *   - [data-child-selector-list] the container for child buttons
 *   - [data-child-selector-add] the "Add New" button
 *   - [data-child-selector-dialog] the dialog for adding a new child
 *   - [data-child-selector-name] the name input inside the dialog
 *   - [data-child-selector-confirm] the "Add" button inside the dialog
 *   - [data-child-selector-cancel] the "Cancel" button inside the dialog
 */
document.querySelectorAll('[data-child-selector]').forEach(container => {
    const list = container.querySelector('[data-child-selector-list]')
    const addBtn = container.querySelector('[data-child-selector-add]')
    const dialog = container.querySelector('[data-child-selector-dialog]')
    const nameInput = container.querySelector('[data-child-selector-name]')
    const cancelBtn = container.querySelector('[data-child-selector-cancel]')
    if (!list || !addBtn || !dialog || !nameInput) return

    const form = container.closest('form')
    let warningDismissed = false
    let warningEl = null

    // Toggle active state when checkbox buttons are clicked
    list.addEventListener('change', e => {
        if (e.target.matches('.child-selector__checkbox')) {
            e.target.closest('.child-selector__btn').classList.toggle(
                'child-selector__btn--active',
                e.target.checked
            )
            hideWarning()
        }
    })

    // Open dialog
    addBtn.addEventListener('click', () => {
        nameInput.value = ''
        dialog.showModal()
        nameInput.focus()
    })

    // Cancel dialog
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => dialog.close())
    }

    // Handle dialog form submit (the "Add" button)
    dialog.querySelector('.child-selector__dialog-form').addEventListener('submit', e => {
        e.preventDefault()
        const name = nameInput.value.trim()
        if (!name) return

        // Check for duplicate names
        const existing = list.querySelectorAll('.child-selector__btn')
        for (const btn of existing) {
            if (btn.textContent.trim().toLowerCase() === name.toLowerCase()) {
                // Activate existing button if not already active
                const checkbox = btn.querySelector('.child-selector__checkbox')
                if (checkbox && !checkbox.checked) {
                    checkbox.checked = true
                    btn.classList.add('child-selector__btn--active')
                }
                dialog.close()
                hideWarning()
                return
            }
        }

        // Create new child button with hidden input
        const label = document.createElement('label')
        label.className = 'child-selector__btn child-selector__btn--active'

        const checkbox = document.createElement('input')
        checkbox.type = 'checkbox'
        checkbox.checked = true
        checkbox.className = 'child-selector__checkbox'
        // New children use a separate input name so the server can create them
        checkbox.name = 'new_children[]'
        checkbox.value = name

        label.appendChild(checkbox)
        label.appendChild(document.createTextNode(' ' + name))
        list.appendChild(label)

        dialog.close()
        hideWarning()
    })

    // Close dialog on backdrop click
    dialog.addEventListener('click', e => {
        if (e.target === dialog) dialog.close()
    })

    // Submit interception: warn if no children selected
    if (form) {
        form.addEventListener('submit', e => {
            const anyChecked = list.querySelector('.child-selector__checkbox:checked')
            if (!anyChecked && !warningDismissed) {
                e.preventDefault()
                showWarning()
                warningDismissed = true
                return
            }
            warningDismissed = false
        })
    }

    function showWarning () {
        if (!warningEl) {
            warningEl = document.createElement('p')
            warningEl.className = 'child-selector__warning'
            warningEl.textContent = 'No children tagged. Submit again to confirm.'
        }
        // Insert warning right before the submit button area
        const submitArea = form.querySelector('.memory-form__submit')
        if (submitArea && !submitArea.previousElementSibling?.classList?.contains('child-selector__warning')) {
            submitArea.parentNode.insertBefore(warningEl, submitArea)
        }
    }

    function hideWarning () {
        warningDismissed = false
        if (warningEl && warningEl.parentNode) {
            warningEl.remove()
        }
    }
})
