import { Controller } from '@hotwired/stimulus';

/**
 * Generic drag-and-drop wrapper around a plain <input type="file">.
 * Clicking or dropping onto the zone forwards files to the hidden input
 * and fires a native 'change' event so any existing input listeners
 * (or plain form submission) behave exactly as if the user had used
 * the native file picker.
 */
export default class extends Controller {
    static targets = ['zone', 'input', 'fileList'];

    connect() {
        this.zoneTarget.addEventListener('click', () => this.inputTarget.click());
        this.zoneTarget.addEventListener('dragover', (e) => this.onDragOver(e));
        this.zoneTarget.addEventListener('dragleave', (e) => this.onDragLeave(e));
        this.zoneTarget.addEventListener('drop', (e) => this.onDrop(e));
        this.inputTarget.addEventListener('change', () => this.renderFileList());
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
            this.inputTarget.files = files;
            this.renderFileList();
        }
    }

    renderFileList() {
        if (!this.hasFileListTarget) return;

        const files = Array.from(this.inputTarget.files ?? []);
        this.fileListTarget.innerHTML = files
            .map((file) => `<li class="text-xs text-slate-600">${this.escapeHtml(file.name)} (${Math.round(file.size / 1024)} KB)</li>`)
            .join('');
    }

    escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }
}
