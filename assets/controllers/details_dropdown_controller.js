import { Controller } from '@hotwired/stimulus';

/**
 * Closes a <details> dropdown when the user clicks anywhere outside of it.
 */
export default class extends Controller {
    connect() {
        this.onDocumentClick = this.onDocumentClick.bind(this);
        document.addEventListener('click', this.onDocumentClick);
    }

    disconnect() {
        document.removeEventListener('click', this.onDocumentClick);
    }

    onDocumentClick(event) {
        if (this.element.open && !this.element.contains(event.target)) {
            this.element.open = false;
        }
    }
}
