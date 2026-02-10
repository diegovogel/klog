/**
 * Media capture: record audio, record video, or take a photo using
 * browser-native MediaRecorder + getUserMedia APIs.
 *
 * Captured files are dispatched as a `media-capture` CustomEvent on the
 * closest [data-media-upload] element so the upload component can add
 * them to its file list.
 *
 * Usage: include <x-media-capture /> inside <x-media-upload />.
 */

const MAX_DURATION_MS = 5 * 60 * 1000 // 5 minutes

document.querySelectorAll('[data-media-capture]').forEach(container => {
    const dialog = document.querySelector('[data-media-capture-dialog]')
    const uploadEl = container.closest('[data-media-upload]')
    if (!dialog || !uploadEl) return

    // Dialog elements
    const titleEl = dialog.querySelector('[data-capture-title]')
    const bodyEl = dialog.querySelector('[data-capture-body]')
    const videoPreview = dialog.querySelector('[data-capture-video-preview]')
    const canvas = dialog.querySelector('[data-capture-canvas]')
    const photoPreview = dialog.querySelector('[data-capture-photo-preview]')
    const waveformCanvas = dialog.querySelector('[data-capture-waveform]')
    const timerEl = dialog.querySelector('[data-capture-timer]')
    const errorEl = dialog.querySelector('[data-capture-error]')
    const btnStart = dialog.querySelector('[data-capture-start]')
    const btnStop = dialog.querySelector('[data-capture-stop]')
    const btnSnap = dialog.querySelector('[data-capture-snap]')
    const btnRetake = dialog.querySelector('[data-capture-retake]')
    const btnUse = dialog.querySelector('[data-capture-use]')
    const btnClose = dialog.querySelector('[data-capture-close]')

    let stream = null
    let recorder = null
    let chunks = []
    let timerInterval = null
    let startTime = null
    let maxTimeout = null
    let animFrameId = null
    let analyser = null
    let capturedBlob = null
    let currentMode = null

    // Button handlers
    container.querySelectorAll('[data-capture-mode]').forEach(btn => {
        btn.addEventListener('click', () => open(btn.dataset.captureMode))
    })

    btnClose.addEventListener('click', close)
    dialog.addEventListener('close', cleanup)

    btnStart.addEventListener('click', startRecording)
    btnStop.addEventListener('click', stopRecording)
    btnSnap.addEventListener('click', snapPhoto)
    btnRetake.addEventListener('click', retakePhoto)
    btnUse.addEventListener('click', usePhoto)

    async function open (mode) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Media capture is not available. Make sure you are using HTTPS.')
            return
        }

        currentMode = mode
        resetUI()

        const constraints = buildConstraints(mode)
        const title = mode === 'audio' ? 'Record Audio'
            : mode === 'video' ? 'Record Video'
                : 'Take Photo'
        titleEl.textContent = title

        dialog.showModal()

        try {
            stream = await navigator.mediaDevices.getUserMedia(constraints)
        } catch (err) {
            showError(permissionMessage(err))
            return
        }

        if (mode === 'audio') {
            setupAudioMode()
        } else if (mode === 'video') {
            setupVideoMode()
        } else {
            setupPhotoMode()
        }
    }

    function buildConstraints (mode) {
        if (mode === 'audio') {
            return { audio: true }
        }
        if (mode === 'video') {
            return { video: true, audio: true }
        }
        // photo — prefer rear camera on mobile
        return { video: { facingMode: 'environment' } }
    }

    // ── Audio ────────────────────────────────────────────────────────────

    function setupAudioMode () {
        waveformCanvas.hidden = false
        btnStart.hidden = false
        setupWaveform()
    }

    function setupWaveform () {
        const ctx = new AudioContext()
        const source = ctx.createMediaStreamSource(stream)
        analyser = ctx.createAnalyser()
        analyser.fftSize = 256
        source.connect(analyser)
        drawWaveform()
    }

    function drawWaveform () {
        if (!analyser) return
        animFrameId = requestAnimationFrame(drawWaveform)

        const bufferLength = analyser.frequencyBinCount
        const dataArray = new Uint8Array(bufferLength)
        analyser.getByteTimeDomainData(dataArray)

        const cvs = waveformCanvas
        const ctx = cvs.getContext('2d')
        const width = cvs.width
        const height = cvs.height

        ctx.fillStyle = getComputedStyle(document.documentElement)
            .getPropertyValue('--pico-form-element-background-color') || '#1a1a2e'
        ctx.fillRect(0, 0, width, height)

        ctx.lineWidth = 2
        ctx.strokeStyle = getComputedStyle(document.documentElement)
            .getPropertyValue('--pico-primary') || '#3b82f6'
        ctx.beginPath()

        const sliceWidth = width / bufferLength
        let x = 0

        for (let i = 0; i < bufferLength; i++) {
            const v = dataArray[i] / 128.0
            const y = (v * height) / 2
            if (i === 0) {
                ctx.moveTo(x, y)
            } else {
                ctx.lineTo(x, y)
            }
            x += sliceWidth
        }
        ctx.lineTo(width, height / 2)
        ctx.stroke()
    }

    // ── Video ────────────────────────────────────────────────────────────

    function setupVideoMode () {
        videoPreview.srcObject = stream
        videoPreview.hidden = false
        btnStart.hidden = false
    }

    // ── Photo ────────────────────────────────────────────────────────────

    function setupPhotoMode () {
        videoPreview.srcObject = stream
        videoPreview.hidden = false
        btnSnap.hidden = false
    }

    function snapPhoto () {
        const track = stream.getVideoTracks()[0]
        const settings = track.getSettings()
        const w = settings.width || videoPreview.videoWidth
        const h = settings.height || videoPreview.videoHeight

        canvas.width = w
        canvas.height = h
        canvas.getContext('2d').drawImage(videoPreview, 0, 0, w, h)

        canvas.toBlob(blob => {
            capturedBlob = blob

            photoPreview.src = URL.createObjectURL(blob)
            photoPreview.hidden = false
            videoPreview.hidden = true
            btnSnap.hidden = true
            btnRetake.hidden = false
            btnUse.hidden = false
        }, 'image/jpeg', 0.92)
    }

    function retakePhoto () {
        if (photoPreview.src) URL.revokeObjectURL(photoPreview.src)
        photoPreview.hidden = true
        capturedBlob = null

        videoPreview.hidden = false
        btnSnap.hidden = false
        btnRetake.hidden = true
        btnUse.hidden = true
    }

    function usePhoto () {
        if (!capturedBlob) return
        const file = new File([capturedBlob], generateFilename('photo', 'jpg'), { type: 'image/jpeg' })
        dispatchFile(file)
        close()
    }

    // ── Recording (shared by audio & video) ──────────────────────────────

    /**
     * Pick a MIME type the browser actually supports.
     * Safari does not support WebM — it uses MP4 containers instead.
     */
    function pickRecorderMimeType (mode) {
        const candidates = mode === 'audio'
            ? ['audio/webm', 'audio/mp4']
            : ['video/webm', 'video/mp4']

        for (const type of candidates) {
            if (MediaRecorder.isTypeSupported(type)) {
                return type
            }
        }
        // Fallback: let the browser decide
        return undefined
    }

    function extForMime (mimeType) {
        if (mimeType && mimeType.includes('mp4')) return 'mp4'
        return 'webm'
    }

    function startRecording () {
        const mimeType = pickRecorderMimeType(currentMode)
        chunks = []

        const options = mimeType ? { mimeType } : {}
        recorder = new MediaRecorder(stream, options)
        recorder.ondataavailable = e => {
            if (e.data.size > 0) chunks.push(e.data)
        }
        recorder.onstop = onRecordingStopped

        recorder.start()
        startTime = Date.now()

        btnStart.hidden = true
        btnStop.hidden = false
        timerEl.hidden = false
        updateTimer()
        timerInterval = setInterval(updateTimer, 1000)

        maxTimeout = setTimeout(() => stopRecording(), MAX_DURATION_MS)
    }

    function stopRecording () {
        if (recorder && recorder.state === 'recording') {
            recorder.stop()
        }
        clearInterval(timerInterval)
        clearTimeout(maxTimeout)
    }

    function onRecordingStopped () {
        // Use the actual MIME type the recorder used, but override the
        // category (audio vs video) based on the capture mode. This
        // handles the WebM container issue where audio-only recordings
        // may be reported as video/webm.
        const actualMime = recorder.mimeType
        const ext = extForMime(actualMime)
        let mimeType

        if (currentMode === 'audio') {
            // Force audio/* regardless of what the recorder reports
            mimeType = actualMime.replace(/^video\//, 'audio/')
        } else {
            mimeType = actualMime.startsWith('video/') ? actualMime : 'video/' + ext
        }

        const prefix = currentMode === 'audio' ? 'audio' : 'video'
        const blob = new Blob(chunks, { type: mimeType })
        const file = new File([blob], generateFilename(prefix, ext), { type: mimeType })
        dispatchFile(file)
        close()
    }

    // ── Timer ────────────────────────────────────────────────────────────

    function formatTime (totalSeconds) {
        const mins = Math.floor(totalSeconds / 60)
        const secs = String(totalSeconds % 60).padStart(2, '0')
        return `${mins}:${secs}`
    }

    function updateTimer () {
        const elapsed = Math.floor((Date.now() - startTime) / 1000)
        const maxSeconds = Math.floor(MAX_DURATION_MS / 1000)
        const remaining = maxSeconds - elapsed
        timerEl.textContent = `${formatTime(elapsed)} / ${formatTime(maxSeconds)}`

        if (remaining <= 30) {
            timerEl.classList.add('is-warning')
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    function generateFilename (prefix, ext) {
        const d = new Date()
        const stamp = [
            d.getFullYear(),
            String(d.getMonth() + 1).padStart(2, '0'),
            String(d.getDate()).padStart(2, '0'),
            '-',
            String(d.getHours()).padStart(2, '0'),
            String(d.getMinutes()).padStart(2, '0'),
            String(d.getSeconds()).padStart(2, '0'),
        ].join('')
        return `${prefix}-${stamp}.${ext}`
    }

    function dispatchFile (file) {
        uploadEl.dispatchEvent(new CustomEvent('media-capture', { detail: { file } }))
    }

    function showError (message) {
        errorEl.textContent = message
        errorEl.hidden = false
    }

    function permissionMessage (err) {
        if (err.name === 'NotAllowedError') {
            return 'Permission denied. Please allow access to your camera or microphone in your browser settings.'
        }
        if (err.name === 'NotFoundError') {
            return 'No camera or microphone found on this device.'
        }
        return `Could not access media device: ${err.message}`
    }

    function close () {
        dialog.close()
    }

    function cleanup () {
        // Stop all media tracks
        if (stream) {
            stream.getTracks().forEach(t => t.stop())
            stream = null
        }

        // Stop recorder
        if (recorder && recorder.state === 'recording') {
            recorder.stop()
        }
        recorder = null
        chunks = []

        // Clear timers
        clearInterval(timerInterval)
        clearTimeout(maxTimeout)
        timerInterval = null
        maxTimeout = null
        startTime = null

        // Stop waveform animation
        if (animFrameId) {
            cancelAnimationFrame(animFrameId)
            animFrameId = null
        }
        analyser = null

        // Clean up photo blob URL
        if (photoPreview.src) {
            URL.revokeObjectURL(photoPreview.src)
        }
        capturedBlob = null
        currentMode = null
    }

    function resetUI () {
        videoPreview.hidden = true
        videoPreview.srcObject = null
        photoPreview.hidden = true
        photoPreview.src = ''
        waveformCanvas.hidden = true
        timerEl.hidden = true
        timerEl.textContent = `0:00 / ${formatTime(Math.floor(MAX_DURATION_MS / 1000))}`
        timerEl.classList.remove('is-warning')
        errorEl.hidden = true
        errorEl.textContent = ''
        btnStart.hidden = true
        btnStop.hidden = true
        btnSnap.hidden = true
        btnRetake.hidden = true
        btnUse.hidden = true
    }
})
