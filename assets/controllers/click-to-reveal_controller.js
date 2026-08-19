import { Controller } from '@hotwired/stimulus';

/**
 * Generic click-to-reveal: a compact display element hides a real form
 * field until clicked, then swaps to show (and focus) the field in place.
 * Matches the click-to-edit interaction used elsewhere in the admin
 * (e.g. AdminUserDetailComponent), but purely client-side — no round trip
 * is needed since the underlying field already exists in this same form.
 */
export default class extends Controller {
    static targets = ['display', 'field', 'input'];

    reveal() {
        this.displayTarget.classList.add('hidden');
        this.fieldTarget.classList.remove('hidden');

        if (this.hasInputTarget) {
            this.inputTarget.focus();
        }
    }
}
