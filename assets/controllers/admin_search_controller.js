import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'panel'];

    connect() {
        this.onDocumentKeydown = (event) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                this.inputTarget.focus();
            }
            if (event.key === 'Escape') {
                this.close();
            }
        };
        this.onDocumentClick = (event) => {
            if (!this.element.contains(event.target)) this.close();
        };
        document.addEventListener('keydown', this.onDocumentKeydown);
        document.addEventListener('click', this.onDocumentClick);
    }

    disconnect() {
        document.removeEventListener('keydown', this.onDocumentKeydown);
        document.removeEventListener('click', this.onDocumentClick);
    }

    open() {
        if (this.hasPanelTarget) this.panelTarget.classList.remove('hidden');
    }

    close() {
        if (this.hasPanelTarget) this.panelTarget.classList.add('hidden');
    }

    keydown(event) {
        if (event.key === 'Escape') this.inputTarget.blur();
    }
}
