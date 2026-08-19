import { Controller } from '@hotwired/stimulus';

/**
 * Tracks unsaved changes on the article editor form so the "Preview" link,
 * which always shows the last saved draft, can warn before it goes stale.
 */
export default class extends Controller {
    static targets = ['warning', 'form'];

    connect() {
        this.dirty = false;
        this.onChange = this.onChange.bind(this);
        this.onSubmit = this.onSubmit.bind(this);
        this.onBeforeUnload = this.onBeforeUnload.bind(this);
        this.formTarget.addEventListener('input', this.onChange);
        this.formTarget.addEventListener('change', this.onChange);
        this.formTarget.addEventListener('submit', this.onSubmit);
        window.addEventListener('beforeunload', this.onBeforeUnload);
    }

    disconnect() {
        this.formTarget.removeEventListener('input', this.onChange);
        this.formTarget.removeEventListener('change', this.onChange);
        this.formTarget.removeEventListener('submit', this.onSubmit);
        window.removeEventListener('beforeunload', this.onBeforeUnload);
    }

    onChange() {
        this.dirty = true;
        this.warningTargets.forEach((el) => el.classList.remove('hidden'));
    }

    onSubmit() {
        this.dirty = false;
    }

    onBeforeUnload(event) {
        if (this.dirty) {
            event.preventDefault();
        }
    }
}
