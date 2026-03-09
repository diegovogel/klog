/**
 * Media file upload with preview, remove, drag-and-drop, and file counter.
 *
 * Usage: Add data-media-upload to a wrapper element containing:
 *   - [data-media-upload-input] the hidden file input
 *   - [data-media-upload-preview] the preview container
 *   - [data-media-upload-dropzone] the click/drop target
 *   - [data-media-upload-counter] the counter element
 *   - [data-media-upload-count] the current count number
 *   - data-max attribute on the wrapper for the file limit
 */
document.querySelectorAll('[data-media-upload]').forEach(upload => {
    const input = upload.querySelector('[data-media-upload-input]')
    const preview = upload.querySelector('[data-media-upload-preview]')
    const dropzone = upload.querySelector('[data-media-upload-dropzone]')
    const counter = upload.querySelector('[data-media-upload-counter]')
    const countDisplay = upload.querySelector('[data-media-upload-count]')
    const max = parseInt(upload.dataset.max, 10) || 20

    if (!input || !preview || !dropzone) return

    const dataTransfer = new DataTransfer()
    const form = input.closest('form')

    // Clear the input value right before opening the file picker so
    // re-selecting the same file still triggers a change event.
    // We do this on click (before selection) instead of after the
    // change event, so input.files stays populated for form submission.
    input.addEventListener('click', () => {
        input.value = ''
    })

    input.addEventListener('change', () => {
        addFiles(input.files)
    })

    // Safety net: if the user opens the file picker and cancels,
    // input.files will be empty (cleared by the click handler).
    // Re-sync from dataTransfer before submission to restore them.
    if (form) {
        form.addEventListener('submit', () => syncInput())
    }

    // Drag and drop
    dropzone.addEventListener('dragover', e => {
        e.preventDefault()
        dropzone.classList.add('is-dragover')
    })

    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('is-dragover')
    })

    dropzone.addEventListener('drop', e => {
        e.preventDefault()
        dropzone.classList.remove('is-dragover')
        addFiles(e.dataTransfer.files)
    })

    // Accept files from media-capture component
    upload.addEventListener('media-capture', e => {
        if (e.detail && e.detail.file) {
            addFiles([e.detail.file])
        }
    })

    function addFiles (fileList) {
        for (const file of fileList) {
            if (dataTransfer.files.length >= max) break
            if (isDuplicate(file)) continue
            dataTransfer.items.add(file)
        }
        syncInput()
        renderPreviews()
        updateCounter()
    }

    function isDuplicate (file) {
        for (let i = 0; i < dataTransfer.files.length; i++) {
            const existing = dataTransfer.files[i]
            if (existing.name === file.name && existing.size === file.size) {
                return true
            }
        }
        return false
    }

    function removeFile (index) {
        dataTransfer.items.remove(index)
        syncInput()
        renderPreviews()
        updateCounter()
    }

    function syncInput () {
        // Build a fresh DataTransfer to avoid a Chrome quirk where
        // assigning dataTransfer.files directly to input.files creates
        // a link — a subsequent input.value = '' would corrupt both.
        const dt = new DataTransfer()
        for (let i = 0; i < dataTransfer.items.length; i++) {
            const file = dataTransfer.items[i].getAsFile()
            if (file) dt.items.add(file)
        }
        input.files = dt.files
    }

    function updateCounter () {
        const count = dataTransfer.files.length
        if (counter) {
            counter.hidden = count === 0
        }
        if (countDisplay) {
            countDisplay.textContent = count
        }
    }

    function renderPreviews () {
        preview.innerHTML = ''

        for (let i = 0; i < dataTransfer.files.length; i++) {
            const file = dataTransfer.files[i]
            const item = document.createElement('div')
            item.className = 'media-upload__item'

            const thumb = document.createElement('div')
            thumb.className = 'media-upload__thumb'

            if (file.type.startsWith('image/')) {
                const img = document.createElement('img')
                img.src = URL.createObjectURL(file)
                img.alt = file.name
                img.onload = () => URL.revokeObjectURL(img.src)
                thumb.appendChild(img)
            } else if (file.type.startsWith('video/')) {
                const video = document.createElement('video')
                video.src = URL.createObjectURL(file)
                video.muted = true
                video.preload = 'metadata'
                video.addEventListener('loadeddata', () => URL.revokeObjectURL(video.src))
                thumb.appendChild(video)
                const icon = document.createElement('span')
                icon.className = 'media-upload__icon'
                icon.textContent = '\u25B6'
                thumb.appendChild(icon)
            } else if (file.type.startsWith('audio/')) {
                const icon = document.createElement('span')
                icon.className = 'media-upload__icon media-upload__icon--audio'
                icon.textContent = '\u266B'
                thumb.appendChild(icon)
            }

            item.appendChild(thumb)

            const info = document.createElement('div')
            info.className = 'media-upload__info'
            info.textContent = file.name
            item.appendChild(info)

            const removeBtn = document.createElement('button')
            removeBtn.type = 'button'
            removeBtn.className = 'media-upload__remove'
            removeBtn.textContent = '\u00D7'
            removeBtn.title = 'Remove'
            removeBtn.addEventListener('click', () => removeFile(i))
            item.appendChild(removeBtn)

            preview.appendChild(item)
        }
    }
})
