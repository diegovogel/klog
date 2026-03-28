/**
 * Submit Loading Indicator
 *
 * Shows a spinner inside the submit button when a form is actually submitting.
 * Must be imported AFTER other submit interceptors so it runs last in the
 * listener queue — if any interceptor called preventDefault(), we skip.
 */
const form = document.querySelector('.memory-form')
if (form) {
    const btn = form.querySelector('button[type="submit"]')

    form.addEventListener('submit', e => {
        if (e.defaultPrevented) return

        btn.disabled = true
        btn.insertAdjacentHTML('beforeend', '<span class="btn-spinner" aria-hidden="true"></span>')
    })
}
