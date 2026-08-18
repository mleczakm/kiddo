import { Controller } from '@hotwired/stimulus';

/**
 * Manages view preference (grid/calendar) using localStorage.
 */
export default class extends Controller {
    static values = {
        storageKey: { type: String, default: 'kiddo.workshops.view.v2' },
    };

    connect() {
        this.#syncFromStorage();
    }

    toggle(event) {
        const button = event.currentTarget;
        const newView = button.dataset.viewPreference;

        if (newView && (newView === 'grid' || newView === 'calendar')) {
            this.#saveToStorage(newView);
        }
    }

    #syncFromStorage() {
        // A deep link (e.g. a lesson card that navigates straight to a
        // workshop URL) can render the page with a modal already open.
        // Restoring the stored grid/calendar preference goes through the
        // `setView` action, which deliberately clears the modal-open state
        // as part of a manual view switch — so here it would silently close
        // the modal a moment after it opened. Leave the deep link alone.
        if (document.querySelector('dialog[open]')) {
            return;
        }

        const stored = localStorage.getItem(this.storageKeyValue);
        if (stored && (stored === 'grid' || stored === 'calendar')) {
            const liveComponent = this.element.closest('[data-controller*="live"]');
            if (liveComponent) {
                const viewInput = liveComponent.querySelector('[data-model="view"]');
                if (viewInput) {
                    viewInput.value = stored;
                    viewInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }
        }
    }

    #saveToStorage(view) {
        localStorage.setItem(this.storageKeyValue, view);
    }
}
