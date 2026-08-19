/**
 * Shared client-side image optimization: HEIC → JPEG, resize to a max
 * dimension, encode as WebP. Extracted from image_upload_controller.js
 * (workshop thumbnails) so article uploads (dropzone attachments, Tiptap
 * inline images) apply the exact same transformation before the file
 * ever reaches the server.
 */

export const MAX_DIMENSION = 1920;
export const WEBP_QUALITY = 0.85;

/**
 * Convert an image File/Blob to an optimized WebP File.
 * HEIC sources are decoded to JPEG first (canvas cannot draw HEIC directly).
 * Throws on failure — callers decide how to surface that to the user.
 *
 * @param {File} source
 * @returns {Promise<{file: File, width: number, height: number}>}
 */
export async function optimizeImageToWebp(source) {
    let decodable = source;

    try {
        const { isHeic, heicTo } = await import('heic-to');
        if (await isHeic(source)) {
            decodable = await heicTo({ blob: source, type: 'image/jpeg', quality: 0.92 });
        }
    } catch (error) {
        throw new Error('HEIC_DECODE_FAILED', { cause: error });
    }

    const bitmap = await createImageBitmap(decodable, { imageOrientation: 'from-image' });
    const scale = Math.min(1, MAX_DIMENSION / Math.max(bitmap.width, bitmap.height));
    const width = Math.max(1, Math.round(bitmap.width * scale));
    const height = Math.max(1, Math.round(bitmap.height * scale));
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');
    context.drawImage(bitmap, 0, 0, width, height);
    bitmap.close();

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', WEBP_QUALITY));
    if (!blob) {
        throw new Error('WebP encoding is unavailable');
    }

    const file = new File([blob], webpFilename(source.name), {
        type: 'image/webp',
        lastModified: Date.now(),
    });

    return { file, width, height };
}

export function webpFilename(filename) {
    return `${filename.replace(/\.[^.]+$/, '') || 'image'}.webp`;
}

export function formatBytes(bytes) {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
