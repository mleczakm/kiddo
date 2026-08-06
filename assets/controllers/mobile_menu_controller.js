import { Controller } from '@hotwired/stimulus';

/**
 * Toggles the site's mobile navigation drawer (off-canvas, below md).
 */
export default class extends Controller {
    static targets = ['panel', 'backdrop'];

    open() {
        this.panelTarget.classList.add('open');
        this.backdropTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    close() {
        this.panelTarget.classList.remove('open');
        this.backdropTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    toggle() {
        if (this.panelTarget.classList.contains('open')) {
            this.close();
        } else {
            this.open();
        }
    }
}
