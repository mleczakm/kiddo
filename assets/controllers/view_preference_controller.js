import { Controller } from '@hotwired/stimulus';

/**
 * Manages view preference (grid/calendar) using localStorage.
 */
export default class extends Controller {
    static values = {
        storageKey: { type: String, default: 'kiddo.workshops.view' },
    };

    connect() {
        this.#syncFromStorage();
    }

    toggle(event) {
        const button = event.currentTarget;
        const newView = button.dataset.view;

        if (newView && (newView === 'grid' || newView === 'calendar')) {
            this.#saveToStorage(newView);
        }
    }

    #syncFromStorage() {
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
