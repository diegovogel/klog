/**
 * Resize an image File using the Canvas API.
 *
 * - Scales down to fit within maxDimension on the longest edge
 * - Outputs JPEG at the given quality
 * - Skips GIFs (may be animated) and HEIC/HEIF/AVIF (Canvas can't decode)
 * - Returns the original file unchanged if already within bounds or unsupported
 *
 * @param {File} file
 * @param {object} [options]
 * @param {number} [options.maxDimension=2048]
 * @param {number} [options.quality=0.85]
 * @returns {Promise<File>}
 */
export async function resizeImage (file, { maxDimension = 2048, quality = 0.85 } = {}) {
    const skipTypes = ['image/gif', 'image/heic', 'image/heif', 'image/avif']

    if (!file.type.startsWith('image/') || skipTypes.includes(file.type)) {
        return file
    }

    if (typeof createImageBitmap === 'undefined') {
        return file
    }

    let bitmap
    try {
        bitmap = await createImageBitmap(file)
    } catch {
        return file
    }

    const { width, height } = bitmap

    if (width <= maxDimension && height <= maxDimension) {
        bitmap.close()
        return file
    }

    const scale = maxDimension / Math.max(width, height)
    const newWidth = Math.round(width * scale)
    const newHeight = Math.round(height * scale)

    const canvas = document.createElement('canvas')
    canvas.width = newWidth
    canvas.height = newHeight

    const ctx = canvas.getContext('2d')
    ctx.drawImage(bitmap, 0, 0, newWidth, newHeight)
    bitmap.close()

    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality))

    const name = file.name.replace(/\.\w+$/, '.jpg')

    return new File([blob], name, {
        type: 'image/jpeg',
        lastModified: file.lastModified,
    })
}
