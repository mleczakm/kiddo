import { Controller } from '@hotwired/stimulus';
import { optimizeImageToWebp, formatBytes } from '../utils/image_optimizer.js';

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

        try {
            const { file: optimized, width, height } = await optimizeImageToWebp(source);

            const transfer = new DataTransfer();
            transfer.items.add(optimized);
            this.inputTarget.files = transfer.files;

            this.showPreview(optimized, width, height);
            this.showStatus(
                `Gotowe: ${width} × ${height} px, ${formatBytes(source.size)} → ${formatBytes(optimized.size)} (WebP).`,
            );
        } catch (error) {
            if (error.message === 'HEIC_DECODE_FAILED') {
                this.rejectFile('Nie udało się odczytać zdjęcia HEIC. Zapisz je jako JPG lub PNG i spróbuj ponownie.');
            } else {
                this.rejectFile('Nie udało się przetworzyć zdjęcia. Wybierz plik JPG, PNG lub WebP.');
            }
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
        this.showStatus(`Gotowe: ${formatBytes(source.size)} (wideo, bez zmian).`);
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

}
