import { Controller } from '@hotwired/stimulus';

/**
 * Grows a textarea's height to fit its content instead of scrolling inside
 * a fixed box. Reusable across any plain (non-tiptap) textarea.
 */
export default class extends Controller {
    connect() {
        this.resize();
    }

    resize() {
        this.element.style.height = 'auto';
        this.element.style.height = `${this.element.scrollHeight}px`;
    }
}
