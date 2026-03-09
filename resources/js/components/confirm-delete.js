document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-confirm-delete] button[type="submit"]')
    if (!btn) return

    const form = btn.closest('[data-confirm-delete]')

    if (form.dataset.confirmState !== 'armed') {
        e.preventDefault()
        form.dataset.confirmState = 'armed'
        btn.textContent = 'Click to confirm'
        btn.classList.add('memory-card__delete-btn--confirm')

        const reset = () => {
            form.dataset.confirmState = ''
            btn.textContent = 'Delete'
            btn.classList.remove('memory-card__delete-btn--confirm')
            clearTimeout(timer)
            document.removeEventListener('click', outsideClick, true)
        }

        const outsideClick = (evt) => {
            if (!btn.contains(evt.target)) {
                reset()
            }
        }

        const timer = setTimeout(reset, 3000)
        // Defer so the current click doesn't immediately trigger the outside listener
        requestAnimationFrame(() => {
            document.addEventListener('click', outsideClick, true)
        })
    }
})
