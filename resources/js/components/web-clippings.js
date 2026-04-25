/**
 * Web clippings repeater. Container element opts in via:
 *   - [data-web-clippings] wrapper
 *   - [data-web-clippings-list] row container
 *   - [data-web-clippings-add] "Add Clipping" button
 *   - [data-url-check-url] endpoint that probes URL reachability
 */
document.querySelectorAll('[data-web-clippings]').forEach(container => {
    const list = container.querySelector('[data-web-clippings-list]')
    const addBtn = container.querySelector('[data-web-clippings-add]')
    if (!list || !addBtn) return

    const urlCheckUrl = container.dataset.urlCheckUrl

    addBtn.addEventListener('click', () => addRow(list, urlCheckUrl))
})

function addRow (list, urlCheckUrl) {
    const group = document.createElement('div')
    group.className = 'web-clippings__group'

    const row = document.createElement('div')
    row.className = 'web-clippings__row'

    const input = document.createElement('input')
    input.type = 'url'
    input.name = 'clippings[]'
    input.placeholder = 'https://example.com'
    input.className = 'web-clippings__input'

    const message = document.createElement('div')
    message.className = 'web-clippings__message'
    message.hidden = true

    const ui = wireValidation(input, message, urlCheckUrl)

    const removeBtn = document.createElement('button')
    removeBtn.type = 'button'
    removeBtn.className = 'web-clippings__remove'
    removeBtn.textContent = '\u00D7'
    removeBtn.title = 'Remove'
    removeBtn.addEventListener('click', () => {
        ui.cancel()
        group.remove()
    })

    row.appendChild(input)
    row.appendChild(removeBtn)
    group.appendChild(row)
    group.appendChild(message)
    list.appendChild(group)

    input.focus()
}

function wireValidation (input, message, urlCheckUrl) {
    let abortController = null

    const clear = () => {
        message.hidden = true
        message.textContent = ''
        message.className = 'web-clippings__message'
    }

    const showWarning = (text) => {
        message.className = 'web-clippings__message web-clippings__message--warning'
        message.textContent = text
        message.hidden = false
    }

    const showSuggestion = (suggested) => {
        message.className = 'web-clippings__message web-clippings__message--suggestion'
        message.replaceChildren()

        const phrase = document.createElement('span')
        phrase.className = 'web-clippings__suggestion-text'
        phrase.append('Did you mean ')
        const code = document.createElement('code')
        code.className = 'web-clippings__suggestion-url'
        code.textContent = suggested
        phrase.append(code, '?')
        message.append(phrase)

        const btn = document.createElement('button')
        btn.type = 'button'
        btn.className = 'web-clippings__suggestion-apply'
        btn.textContent = 'Use this URL'
        btn.addEventListener('click', () => {
            input.value = suggested
            input.focus()
            evaluate()
        })
        message.append(btn)

        message.hidden = false
    }

    const cancel = () => {
        abortController?.abort()
        abortController = null
    }

    function evaluate () {
        cancel()
        const raw = input.value.trim()

        if (raw === '') {
            clear()
            return
        }

        if (!isValidHttpUrl(raw)) {
            if (looksLikePartialUrl(raw)) {
                showSuggestion(`https://${raw}`)
            } else {
                clear()
            }
            return
        }

        abortController = new AbortController()
        probeUrl(urlCheckUrl, raw, abortController.signal)
            .then(result => {
                if (input.value.trim() !== raw) return
                if (result.ok) {
                    clear()
                } else if (result.reason === 'auth') {
                    showWarning('That page requires signing in. Only public pages work here.')
                } else {
                    showWarning('That page seems to be down or inaccessible.')
                }
            })
            .catch(() => { /* aborted or transient — ignore */ })
    }

    input.addEventListener('input', clear)
    input.addEventListener('blur', evaluate)

    return { cancel }
}

function isValidHttpUrl (raw) {
    try {
        const u = new URL(raw)
        return u.protocol === 'http:' || u.protocol === 'https:'
    } catch {
        return false
    }
}

function looksLikePartialUrl (raw) {
    if (/\s/.test(raw)) return false
    if (/^[a-z][a-z0-9+.-]*:/i.test(raw)) return false
    return /^[a-z0-9][\w.-]*\.[a-z]{2,}([:/?#].*)?$/i.test(raw)
}

function probeUrl (endpoint, url, signal) {
    return window.axios.get(endpoint, { params: { url }, signal })
        .then(res => res.data)
}
