/**
 * Upload a file in chunks via AJAX.
 *
 * @param {File} file
 * @param {object} options
 * @param {string} options.initUrl - URL for POST /uploads/init
 * @param {function} options.chunkUrl - Function (uploadId) => URL for chunk endpoint
 * @param {number} [options.chunkSize=2097152] - Bytes per chunk (default 2 MB)
 * @param {function} [options.onProgress] - Callback (percent 0-100)
 * @param {AbortSignal} [options.signal] - For cancellation
 * @returns {Promise<string>} Resolves with upload_id
 */
export async function uploadFileChunked (file, { initUrl, chunkUrl, chunkSize = 2 * 1024 * 1024, onProgress, signal }) {
    const totalChunks = Math.ceil(file.size / chunkSize)

    const initResponse = await window.axios.post(initUrl, {
        original_filename: file.name,
        mime_type: file.type,
        total_size: file.size,
        total_chunks: totalChunks,
    }, { signal })

    const uploadId = initResponse.data.upload_id

    for (let i = 0; i < totalChunks; i++) {
        const start = i * chunkSize
        const end = Math.min(start + chunkSize, file.size)
        const chunk = file.slice(start, end)

        const formData = new FormData()
        formData.append('chunk', chunk, 'chunk.bin')
        formData.append('chunk_index', i)

        await uploadWithRetry(
            () => window.axios.post(chunkUrl(uploadId), formData, { signal }),
        )

        if (onProgress) {
            onProgress(Math.round(((i + 1) / totalChunks) * 100))
        }
    }

    return uploadId
}

/**
 * Cancel an upload session on the server.
 *
 * @param {string} cancelUrl - DELETE URL for the upload session
 */
export async function cancelUpload (cancelUrl) {
    try {
        await window.axios.delete(cancelUrl)
    } catch {
        // Best effort — server cleanup will catch orphans
    }
}

async function uploadWithRetry (fn, maxRetries = 3) {
    for (let attempt = 0; attempt <= maxRetries; attempt++) {
        try {
            return await fn()
        } catch (err) {
            if (err.name === 'CanceledError' || err.name === 'AbortError') {
                throw err
            }
            if (attempt === maxRetries) {
                throw err
            }
            await delay(1000 * Math.pow(2, attempt))
        }
    }
}

function delay (ms) {
    return new Promise(resolve => setTimeout(resolve, ms))
}
