import { Controller } from '@hotwired/stimulus';

/**
 * Closes an open Live Component actions dropdown when the user clicks
 * outside of it or presses Escape, by delegating to a hidden trigger
 * that carries the actual `closeActions` live-action binding.
 */
export default class extends Controller {
    connect() {
        this.onDocumentClick = this.onDocumentClick.bind(this);
        this.onKeydown = this.onKeydown.bind(this);
        document.addEventListener('click', this.onDocumentClick);
        document.addEventListener('keydown', this.onKeydown);
    }

    disconnect() {
        document.removeEventListener('click', this.onDocumentClick);
        document.removeEventListener('keydown', this.onKeydown);
    }

    onDocumentClick(event) {
        if (this.element.contains(event.target)) {
            return;
        }
        this.close();
    }

    onKeydown(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }

    close() {
        this.element.querySelector('[data-live-action-param="closeActions"]')?.click();
    }
}
