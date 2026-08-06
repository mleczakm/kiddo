import { Controller } from '@hotwired/stimulus';

/**
 * Toggles the admin CRM sidebar as an off-canvas drawer below the md breakpoint.
 */
export default class extends Controller {
    static targets = ['panel', 'backdrop'];

    open() {
        this.panelTarget.classList.remove('-translate-x-full');
        this.backdropTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    close() {
        this.panelTarget.classList.add('-translate-x-full');
        this.backdropTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    toggle() {
        if (this.panelTarget.classList.contains('-translate-x-full')) {
            this.open();
        } else {
            this.close();
        }
    }
}
