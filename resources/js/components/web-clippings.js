/**
 * Web clippings repeater: add/remove URL input rows.
 *
 * Usage: Add data-web-clippings to a wrapper element containing:
 *   - [data-web-clippings-list] the container for clipping rows
 *   - [data-web-clippings-add] the "Add Clipping" button
 */
document.querySelectorAll('[data-web-clippings]').forEach(container => {
    const list = container.querySelector('[data-web-clippings-list]')
    const addBtn = container.querySelector('[data-web-clippings-add]')
    if (!list || !addBtn) return

    addBtn.addEventListener('click', () => addRow())

    function addRow () {
        const row = document.createElement('div')
        row.className = 'web-clippings__row'

        const input = document.createElement('input')
        input.type = 'url'
        input.name = `clippings[]`
        input.placeholder = 'https://example.com'
        input.className = 'web-clippings__input'

        const removeBtn = document.createElement('button')
        removeBtn.type = 'button'
        removeBtn.className = 'web-clippings__remove'
        removeBtn.textContent = '\u00D7'
        removeBtn.title = 'Remove'
        removeBtn.addEventListener('click', () => {
            row.remove()
        })

        row.appendChild(input)
        row.appendChild(removeBtn)
        list.appendChild(row)

        input.focus()
    }
})
