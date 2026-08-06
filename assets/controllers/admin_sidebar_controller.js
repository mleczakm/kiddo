import { Controller } from '@hotwired/stimulus';

/**
 * Toggles the admin CRM sidebar as an off-canvas drawer below the md breakpoint.
 *
 * Drives the drawer via an inline `transform` rather than toggling the
 * `-translate-x-full` utility class: that class and the `md:translate-x-0`
 * override both set the same `translate` property, and depending on how the
 * compiled Tailwind bundle lays out duplicate/legacy utility rules, the
 * unconditional class can end up losing to the responsive one even below the
 * md breakpoint, leaving the drawer visually open after close(). Setting the
 * transform directly (only below md, cleared above it) is unambiguous
 * regardless of the utility CSS's own correctness.
 */
export default class extends Controller {
    static targets = ['panel', 'backdrop'];

    #open = false;

    connect() {
        this.resizeHandler = () => this.#apply();
        window.addEventListener('resize', this.resizeHandler);
    }

    disconnect() {
        window.removeEventListener('resize', this.resizeHandler);
    }

    open() {
        this.#open = true;
        this.#apply();
    }

    close() {
        this.#open = false;
        this.#apply();
    }

    toggle() {
        this.#open = !this.#open;
        this.#apply();
    }

    #apply() {
        const isMobile = window.innerWidth < 768;

        if (!isMobile) {
            this.panelTarget.style.transform = '';
            this.backdropTarget.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            return;
        }

        this.panelTarget.style.transform = this.#open ? 'translateX(0)' : 'translateX(-100%)';
        this.backdropTarget.classList.toggle('hidden', !this.#open);
        document.body.classList.toggle('overflow-hidden', this.#open);
    }
}
