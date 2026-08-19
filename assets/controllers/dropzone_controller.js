import { Controller } from '@hotwired/stimulus';
import { optimizeImageToWebp, formatBytes } from '../utils/image_optimizer.js';

/**
 * Generic drag-and-drop wrapper around a plain <input type="file">.
 * Clicking or dropping onto the zone forwards files to the hidden input
 * and fires a native 'change' event so any existing input listeners
 * (or plain form submission) behave exactly as if the user had used
 * the native file picker.
 *
 * Image files are converted to optimized WebP client-side before they
 * ever reach the input, same as workshop thumbnail uploads (see
 * image_upload_controller.js / utils/image_optimizer.js). Videos and
 * documents pass through unchanged.
 */
export default class extends Controller {
    static targets = ['zone', 'input', 'fileList'];

    connect() {
        this.zoneTarget.addEventListener('click', () => this.inputTarget.click());
        this.zoneTarget.addEventListener('dragover', (e) => this.onDragOver(e));
        this.zoneTarget.addEventListener('dragleave', (e) => this.onDragLeave(e));
        this.zoneTarget.addEventListener('drop', (e) => this.onDrop(e));
        this.inputTarget.addEventListener('change', () => this.handleFiles(this.inputTarget.files));
    }

    onDragOver(event) {
        event.preventDefault();
        this.zoneTarget.classList.add('border-indigo-500', 'bg-indigo-50');
    }

    onDragLeave(event) {
        event.preventDefault();
        this.zoneTarget.classList.remove('border-indigo-500', 'bg-indigo-50');
    }

    onDrop(event) {
        event.preventDefault();
        this.zoneTarget.classList.remove('border-indigo-500', 'bg-indigo-50');

        const files = event.dataTransfer?.files;
        if (files && files.length > 0) {
            this.handleFiles(files);
        }
    }

    async handleFiles(fileList) {
        const files = Array.from(fileList ?? []);
        if (files.length === 0) {
            this.renderFileList([]);
            return;
        }

        this.renderStatus('Optymalizowanie zdjęć…');

        const processed = await Promise.all(files.map((file) => this.processFile(file)));

        const transfer = new DataTransfer();
        processed.forEach((file) => transfer.items.add(file));
        this.inputTarget.files = transfer.files;

        this.renderFileList(processed);
    }

    async processFile(file) {
        if (!file.type.startsWith('image/')) {
            return file;
        }

        try {
            const { file: optimized } = await optimizeImageToWebp(file);
            return optimized;
        } catch (error) {
            // Fall back to the original file — server-side MIME sniffing
            // and the upload policy remain the real validation boundary.
            return file;
        }
    }

    renderFileList(files) {
        if (!this.hasFileListTarget) return;

        this.fileListTarget.innerHTML = files
            .map((file) => `<li class="text-xs text-slate-600">${this.escapeHtml(file.name)} (${formatBytes(file.size)})</li>`)
            .join('');
    }

    renderStatus(message) {
        if (!this.hasFileListTarget) return;
        this.fileListTarget.innerHTML = `<li class="text-xs text-slate-500">${this.escapeHtml(message)}</li>`;
    }

    escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }
}
