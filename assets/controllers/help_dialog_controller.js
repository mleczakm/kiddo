import { Controller } from '@hotwired/stimulus';

/** Loads the requested help article into one shared, accessible modal dialog. */
export default class extends Controller {
    static targets = ['dialog', 'title', 'loading', 'content', 'error'];

    #opener = null;
    #request = null;

    async open(event) {
        const url = event.params.url;
        if (!url) {
            return;
        }

        this.#opener = event.currentTarget;
        this.#request?.abort();
        this.#request = new AbortController();
        this.#showLoading();

        if (!this.dialogTarget.open) {
            this.dialogTarget.showModal();
        }

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: this.#request.signal,
            });
            if (!response.ok) {
                throw new Error(`Help request failed with status ${response.status}`);
            }

            this.contentTarget.innerHTML = await response.text();
            const article = this.contentTarget.querySelector('[data-help-title]');
            this.titleTarget.textContent = article?.dataset.helpTitle || 'Pomoc';
            this.loadingTarget.classList.add('hidden');
            this.contentTarget.classList.remove('hidden');
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            this.loadingTarget.classList.add('hidden');
            this.errorTarget.classList.remove('hidden');
        }
    }

    close() {
        this.#request?.abort();
        if (this.dialogTarget.open) {
            this.dialogTarget.close();
        }
        this.#opener?.focus();
    }

    closeFromBackdrop(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }

    disconnect() {
        this.#request?.abort();
        if (this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }

    #showLoading() {
        this.titleTarget.textContent = 'Pomoc';
        this.contentTarget.replaceChildren();
        this.contentTarget.classList.add('hidden');
        this.errorTarget.classList.add('hidden');
        this.loadingTarget.classList.remove('hidden');
    }
}
