import { Controller } from '@hotwired/stimulus';

const MAX_DIMENSION = 1920;
const WEBP_QUALITY = 0.85;
const MAX_VIDEO_BYTES = 20 * 1024 * 1024;
// video/quicktime (MOV) is deliberately not accepted — most non-Safari
// browsers won't play a <video> reporting that mime type even when the
// underlying codec is fine, same trap as the HEIC photo issue.
const SUPPORTED_VIDEO_TYPES = ['video/mp4', 'video/webm'];

export default class extends Controller {
    static targets = ['input', 'preview', 'videoPreview', 'details', 'status'];

    connect() {
        this.objectUrl = null;
    }

    disconnect() {
        this.revokeObjectUrl();
    }

    async optimize() {
        const source = this.inputTarget.files?.[0];
        if (!source) {
            this.clearPreview();
            return;
        }

        if (source.type.startsWith('video/')) {
            this.handleVideo(source);
            return;
        }

        if (!source.type.startsWith('image/')) {
            this.rejectFile('Wybrany plik nie jest obrazem ani filmem.');
            return;
        }

        this.showStatus('Optymalizowanie zdjęcia…');

        let decodable = source;
        try {
            const { isHeic, heicTo } = await import('heic-to');
            if (await isHeic(source)) {
                this.showStatus('Konwertowanie zdjęcia HEIC…');
                decodable = await heicTo({ blob: source, type: 'image/jpeg', quality: 0.92 });
            }
        } catch (error) {
            this.rejectFile('Nie udało się odczytać zdjęcia HEIC. Zapisz je jako JPG lub PNG i spróbuj ponownie.');
            return;
        }

        try {
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

            const optimized = new File([blob], this.webpFilename(source.name), {
                type: 'image/webp',
                lastModified: Date.now(),
            });
            const transfer = new DataTransfer();
            transfer.items.add(optimized);
            this.inputTarget.files = transfer.files;

            this.showPreview(optimized, width, height);
            this.showStatus(
                `Gotowe: ${width} × ${height} px, ${this.formatBytes(source.size)} → ${this.formatBytes(optimized.size)} (WebP).`,
            );
        } catch (error) {
            this.rejectFile('Nie udało się przetworzyć zdjęcia. Wybierz plik JPG, PNG lub WebP.');
        }
    }

    handleVideo(source) {
        if (!SUPPORTED_VIDEO_TYPES.includes(source.type)) {
            this.rejectFile('Nieobsługiwany format wideo. Wybierz plik MP4 lub WebM (nie MOV).');
            return;
        }

        if (source.size > MAX_VIDEO_BYTES) {
            this.rejectFile(`Plik wideo jest za duży (maks. ${MAX_VIDEO_BYTES / (1024 * 1024)} MB).`);
            return;
        }

        this.showVideoPreview(source);
        this.showStatus(`Gotowe: ${this.formatBytes(source.size)} (wideo, bez zmian).`);
    }

    rejectFile(message) {
        this.inputTarget.value = '';
        this.clearPreview();
        this.showStatus(message, true);
    }

    showPreview(file, width = null, height = null) {
        this.revokeObjectUrl();
        this.objectUrl = URL.createObjectURL(file);
        this.videoPreviewTarget.hidden = true;
        this.videoPreviewTarget.removeAttribute('src');
        this.previewTarget.src = this.objectUrl;
        this.previewTarget.hidden = false;
        this.detailsTarget.textContent = width && height ? `${width} × ${height} px` : file.name;
    }

    showVideoPreview(file) {
        this.revokeObjectUrl();
        this.objectUrl = URL.createObjectURL(file);
        this.previewTarget.hidden = true;
        this.previewTarget.removeAttribute('src');
        this.videoPreviewTarget.src = this.objectUrl;
        this.videoPreviewTarget.hidden = false;
        this.detailsTarget.textContent = file.name;
    }

    clearPreview() {
        this.revokeObjectUrl();
        this.previewTarget.removeAttribute('src');
        this.previewTarget.hidden = true;
        this.videoPreviewTarget.removeAttribute('src');
        this.videoPreviewTarget.hidden = true;
        this.detailsTarget.textContent = '';
        this.statusTarget.textContent = '';
    }

    showStatus(message, isError = false) {
        this.statusTarget.textContent = message;
        this.statusTarget.classList.toggle('text-red-600', isError);
        this.statusTarget.classList.toggle('text-slate-500', !isError);
    }

    revokeObjectUrl() {
        if (this.objectUrl) {
            URL.revokeObjectURL(this.objectUrl);
            this.objectUrl = null;
        }
    }

    webpFilename(filename) {
        return `${filename.replace(/\.[^.]+$/, '') || 'workshop'}.webp`;
    }

    formatBytes(bytes) {
        if (bytes < 1024 * 1024) {
            return `${Math.max(1, Math.round(bytes / 1024))} KB`;
        }

        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }
}
