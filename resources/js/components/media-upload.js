import { resizeImage } from '../lib/image-resize.js'
import { uploadFileChunked, cancelUpload } from '../lib/chunked-upload.js'

const DEFAULT_MAX_FILE_SIZE = 500 * 1024 * 1024 // 500 MB fallback

/**
 * File types that browsers generally cannot preview in <img> tags.
 * These get a placeholder icon instead of a blob URL.
 */
const UNPREVIEWABLE_IMAGE_TYPES = ['image/heic', 'image/heif', 'image/avif']

/**
 * Media file upload with preview, remove, drag-and-drop, file counter,
 * client-side image resize, and eager chunked uploading.
 *
 * Usage: Add data-media-upload to a wrapper element containing:
 *   - [data-media-upload-input] the hidden file input
 *   - [data-media-upload-preview] the preview container
 *   - [data-media-upload-dropzone] the click/drop target
 *   - [data-media-upload-counter] the counter element
 *   - [data-media-upload-count] the current count number
 *   - data-max attribute on the wrapper for the file limit
 *   - data-upload-init-url for the chunked upload init endpoint
 *   - data-upload-chunk-url for the chunk endpoint (with __ID__ placeholder)
 *   - data-upload-cancel-url for the cancel endpoint (with __ID__ placeholder)
 */
document.querySelectorAll('[data-media-upload]').forEach(upload => {
    const input = upload.querySelector('[data-media-upload-input]')
    const preview = upload.querySelector('[data-media-upload-preview]')
    const dropzone = upload.querySelector('[data-media-upload-dropzone]')
    const counter = upload.querySelector('[data-media-upload-counter]')
    const countDisplay = upload.querySelector('[data-media-upload-count]')
    const max = parseInt(upload.dataset.max, 10) || 20

    if (!input || !preview || !dropzone) return

    const form = input.closest('form')
    const initUrl = upload.dataset.uploadInitUrl
    const chunkUrlTemplate = upload.dataset.uploadChunkUrl
    const cancelUrlTemplate = upload.dataset.uploadCancelUrl
    const chunkedEnabled = initUrl && chunkUrlTemplate && cancelUrlTemplate
    const maxFileSize = parseInt(upload.dataset.uploadMaxFileSize, 10) || DEFAULT_MAX_FILE_SIZE

    /**
     * Per-file state entries.
     * Each entry: { file, status, progress, uploadId, abortController, el, errorMsg }
     * status: 'resizing' | 'uploading' | 'uploaded' | 'error'
     */
    const entries = []

    // Warn user before navigating away during uploads
    window.addEventListener('beforeunload', e => {
        const hasActive = entries.some(en => en.status === 'resizing' || en.status === 'uploading')
        if (hasActive) {
            e.preventDefault()
        }
    })

    // Clear the input value right before opening the file picker so
    // re-selecting the same file still triggers a change event.
    input.addEventListener('click', () => {
        input.value = ''
    })

    input.addEventListener('change', () => {
        addFiles(input.files)
    })

    // Form submission: inject upload IDs and wait for pending uploads
    if (form) {
        form.addEventListener('submit', e => {
            // Filter out errored entries — only submit successful uploads
            const pending = entries.filter(en => en.status === 'resizing' || en.status === 'uploading')
            if (pending.length > 0) {
                e.preventDefault()
                showFullPageProgress()
                waitForUploads().then(() => {
                    injectUploadIds()
                    form.submit()
                }).catch(() => {
                    hideFullPageProgress()
                })
                return
            }

            // All done — inject upload IDs and let the form submit
            injectUploadIds()
        })
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

    async function addFiles (fileList) {
        const filesToProcess = []

        for (const raw of fileList) {
            if (entries.length >= max) break
            if (isDuplicate(raw)) continue

            const entry = {
                file: raw,
                status: 'resizing',
                progress: 0,
                uploadId: null,
                abortController: null,
                el: null,
                errorMsg: null,
            }

            // Client-side size check before any processing
            if (raw.size > maxFileSize) {
                entry.status = 'error'
                entry.errorMsg = `Max upload size exceeded (${formatBytes(maxFileSize)})`
            }

            entries.push(entry)
            createPreviewElement(entry)
            filesToProcess.push(entry)
        }

        updateCounter()

        // Process each file: resize then upload eagerly
        for (const entry of filesToProcess) {
            if (entry.status === 'error') {
                updateEntryState(entry)
                continue
            }

            try {
                entry.file = await resizeImage(entry.file)
            } catch {
                // Resize failed — use original file
            }

            if (chunkedEnabled) {
                entry.status = 'uploading'
                entry.abortController = new AbortController()
                updateEntryState(entry)
                startUpload(entry)
            } else {
                entry.status = 'uploaded'
                updateEntryState(entry)
            }
        }
    }

    function startUpload (entry) {
        uploadFileChunked(entry.file, {
            initUrl,
            chunkUrl: id => chunkUrlTemplate.replace('__ID__', id),
            onProgress: percent => {
                entry.progress = percent
                updateProgressFill(entry)
            },
            signal: entry.abortController.signal,
        }).then(uploadId => {
            entry.uploadId = uploadId
            entry.status = 'uploaded'
            entry.progress = 100
            updateEntryState(entry)
        }).catch(err => {
            if (err.name === 'CanceledError' || err.name === 'AbortError') return
            entry.status = 'error'
            entry.errorMsg = extractErrorMessage(err)
            updateEntryState(entry)
        })
    }

    function isDuplicate (file) {
        return entries.some(en => en.file.name === file.name && en.file.size === file.size)
    }

    function removeFile (index) {
        const entry = entries[index]

        // Cancel in-progress upload
        if (entry.abortController) {
            entry.abortController.abort()
        }

        // Clean up server-side if upload was started
        if (entry.uploadId && cancelUrlTemplate) {
            cancelUpload(cancelUrlTemplate.replace('__ID__', entry.uploadId))
        }

        // Revoke blob URLs for videos (images revoke on load, videos keep theirs)
        if (entry.el) {
            const video = entry.el.querySelector('video')
            if (video && video.src.startsWith('blob:')) {
                URL.revokeObjectURL(video.src)
            }
            entry.el.remove()
        }

        entries.splice(index, 1)

        // Re-bind remove buttons with updated indices
        entries.forEach((en, i) => {
            const btn = en.el?.querySelector('.media-upload__remove')
            if (btn) {
                const newBtn = btn.cloneNode(true)
                btn.replaceWith(newBtn)
                newBtn.addEventListener('click', () => removeFile(i))
            }
        })

        updateCounter()
    }

    function injectUploadIds () {
        // Remove any previously injected hidden inputs
        form.querySelectorAll('input[name="uploads[]"]').forEach(el => el.remove())

        // Clear the file input so no raw files are sent
        input.value = ''

        // Add hidden inputs for each completed upload
        for (const entry of entries) {
            if (entry.uploadId) {
                const hidden = document.createElement('input')
                hidden.type = 'hidden'
                hidden.name = 'uploads[]'
                hidden.value = entry.uploadId
                form.appendChild(hidden)
            }
        }
    }

    function waitForUploads (timeoutMs = 5 * 60 * 1000) {
        return new Promise((resolve, reject) => {
            const timeout = setTimeout(() => {
                clearInterval(check)
                reject(new Error('Upload timed out'))
            }, timeoutMs)

            const check = setInterval(() => {
                updateFullPageProgress()

                const active = entries.filter(en => en.status !== 'error')
                const allDone = active.length === 0 || active.every(en => en.status === 'uploaded')
                if (allDone) {
                    clearTimeout(timeout)
                    clearInterval(check)
                    resolve()
                }
            }, 100)
        })
    }

    function updateCounter () {
        const count = entries.length
        if (counter) {
            counter.hidden = count === 0
        }
        if (countDisplay) {
            countDisplay.textContent = count
        }
    }

    // ---- DOM creation & targeted updates ----

    /**
     * Create the DOM element for an entry once, store reference in entry.el.
     * Appends to the preview container.
     */
    function createPreviewElement (entry) {
        const file = entry.file
        const index = entries.indexOf(entry)
        const item = document.createElement('div')
        item.className = 'media-upload__item'
        entry.el = item

        const thumb = document.createElement('div')
        thumb.className = 'media-upload__thumb'

        if (file.type.startsWith('image/') && !UNPREVIEWABLE_IMAGE_TYPES.includes(file.type)) {
            const img = document.createElement('img')
            img.src = URL.createObjectURL(file)
            img.alt = file.name
            img.onload = () => URL.revokeObjectURL(img.src)
            thumb.appendChild(img)
        } else if (file.type.startsWith('image/')) {
            // HEIC/HEIF/AVIF — show a placeholder icon
            const icon = document.createElement('span')
            icon.className = 'media-upload__icon'
            icon.textContent = '\uD83D\uDDBC' // framed picture emoji
            thumb.appendChild(icon)
        } else if (file.type.startsWith('video/')) {
            const video = document.createElement('video')
            video.src = URL.createObjectURL(file)
            video.muted = true
            video.preload = 'metadata'
            // Don't revoke — video needs the blob URL to keep displaying the frame.
            // The URL is revoked when the entry is removed via removeFile().
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

        // Progress bar — always present, hidden via CSS when not needed
        const progress = document.createElement('div')
        progress.className = 'media-upload__progress'
        const fill = document.createElement('div')
        fill.className = 'media-upload__progress-fill'
        fill.style.width = '0%'
        progress.appendChild(fill)
        thumb.appendChild(progress)

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
        removeBtn.addEventListener('click', () => removeFile(entries.indexOf(entry)))
        item.appendChild(removeBtn)

        preview.appendChild(item)
        updateEntryState(entry)
    }

    /**
     * Update an entry's DOM element to reflect its current state.
     * No DOM destruction — only class and style changes.
     */
    function updateEntryState (entry) {
        const item = entry.el
        if (!item) return

        item.classList.toggle('is-uploading', entry.status === 'uploading')
        item.classList.toggle('is-uploaded', entry.status === 'uploaded')
        item.classList.toggle('is-error', entry.status === 'error')

        updateProgressFill(entry)

        // Update info text for error state
        const info = item.querySelector('.media-upload__info')
        if (info) {
            if (entry.status === 'error' && entry.errorMsg) {
                info.textContent = entry.errorMsg
                info.classList.add('media-upload__info--error')
            } else {
                info.textContent = entry.file.name
                info.classList.remove('media-upload__info--error')
            }
        }
    }

    /**
     * Update only the progress bar fill width for an entry.
     */
    function updateProgressFill (entry) {
        if (!entry.el) return
        const fill = entry.el.querySelector('.media-upload__progress-fill')
        if (fill) {
            fill.style.width = entry.progress + '%'
        }
    }

    // ---- Full-page progress overlay ----

    let overlay = null

    function showFullPageProgress () {
        if (overlay) return
        overlay = document.createElement('div')
        overlay.className = 'media-upload-overlay'
        overlay.innerHTML = `
            <div class="media-upload-overlay__content">
                <p class="media-upload-overlay__text">Uploading files\u2026</p>
                <progress class="media-upload-overlay__bar" max="100" value="0"></progress>
            </div>
        `
        document.body.appendChild(overlay)
        updateFullPageProgress()
    }

    function hideFullPageProgress () {
        if (overlay) {
            overlay.remove()
            overlay = null
        }
    }

    function updateFullPageProgress () {
        if (!overlay) return
        const bar = overlay.querySelector('progress')
        if (!bar) return

        // Only count non-errored entries for progress
        const active = entries.filter(en => en.status !== 'error')
        const totalBytes = active.reduce((sum, en) => sum + en.file.size, 0)
        if (totalBytes === 0) {
            bar.value = 100
            return
        }
        const uploadedBytes = active.reduce((sum, en) => sum + (en.file.size * en.progress / 100), 0)
        bar.value = Math.round(uploadedBytes / totalBytes * 100)
    }

    // ---- Helpers ----

    /**
     * Extract a human-readable error message from an Axios error response.
     * Falls back to a generic message if nothing useful is found.
     */
    function extractErrorMessage (err) {
        const data = err.response?.data
        if (data?.errors) {
            // Laravel validation: { errors: { field: ['msg', ...] } }
            const first = Object.values(data.errors).flat()[0]
            if (first) return first
        }
        if (data?.message) {
            return data.message
        }

        return 'Upload failed'
    }

    /**
     * Format bytes into a human-readable string (e.g. "200 MB").
     */
    function formatBytes (bytes) {
        if (bytes >= 1024 * 1024 * 1024) return Math.round(bytes / (1024 * 1024 * 1024)) + ' GB'
        if (bytes >= 1024 * 1024) return Math.round(bytes / (1024 * 1024)) + ' MB'
        if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB'

        return bytes + ' B'
    }
})
