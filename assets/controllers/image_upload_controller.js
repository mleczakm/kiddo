import { Controller } from '@hotwired/stimulus';

const MAX_DIMENSION = 1920;
const WEBP_QUALITY = 0.85;

export default class extends Controller {
    static targets = ['input', 'preview', 'details', 'status'];

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

        if (!source.type.startsWith('image/')) {
            this.showStatus('Wybrany plik nie jest obrazem.', true);
            return;
        }

        this.showStatus('Optymalizowanie zdjęcia…');

        try {
            const bitmap = await createImageBitmap(source, { imageOrientation: 'from-image' });
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
            this.showPreview(source);
            this.showStatus('Nie udało się przekonwertować zdjęcia. Zostanie wysłany oryginalny plik.', true);
        }
    }

    showPreview(file, width = null, height = null) {
        this.revokeObjectUrl();
        this.objectUrl = URL.createObjectURL(file);
        this.previewTarget.src = this.objectUrl;
        this.previewTarget.hidden = false;
        this.detailsTarget.textContent = width && height ? `${width} × ${height} px` : file.name;
    }

    clearPreview() {
        this.revokeObjectUrl();
        this.previewTarget.removeAttribute('src');
        this.previewTarget.hidden = true;
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
