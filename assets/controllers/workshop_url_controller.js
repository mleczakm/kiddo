import { Controller } from '@hotwired/stimulus';

/**
 * Updates the browser URL when opening/closing LessonModal.
 * Uses click actions (not LiveComponent browser events) so the URL
 * changes even when Live morphs the component root.
 */
export default class extends Controller {
    static values = {
        openUrl: String,
        closeUrl: String,
    };

    pushOpen() {
        this.#push(this.openUrlValue);
    }

    pushClose() {
        this.#push(this.closeUrlValue || '/');
    }

    #push(url) {
        if (!url || typeof url !== 'string') {
            return;
        }

        const current = `${window.location.pathname}${window.location.search}`;
        if (current === url) {
            return;
        }

        history.pushState({}, '', url);
    }
}
