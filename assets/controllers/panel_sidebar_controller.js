import { Controller } from '@hotwired/stimulus';

/**
 * Off-canvas drawer for the customer panel sidebar (below md). Toggles the
 * `.open` class the `.panel-drawer` CSS rule keys off; the media query keeps
 * the transform out of md+ so the in-flow (md:static) sidebar is untouched.
 */
export default class extends Controller {
    static targets = ['panel', 'backdrop'];

    open() {
        this.#set(true);
    }

    close() {
        this.#set(false);
    }

    toggle() {
        this.#set(!(this.hasPanelTarget && this.panelTarget.classList.contains('open')));
    }

    #set(open) {
        if (this.hasPanelTarget) {
            this.panelTarget.classList.toggle('open', open);
        }
        if (this.hasBackdropTarget) {
            this.backdropTarget.classList.toggle('hidden', !open);
        }
        document.body.classList.toggle('overflow-hidden', open);
    }
}
