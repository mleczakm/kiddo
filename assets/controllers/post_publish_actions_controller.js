import { Controller } from '@hotwired/stimulus';

/**
 * Warns when the "planned publication" datetime is in the future, since
 * clicking "Save & publish" then schedules rather than publishing
 * immediately — easy to miss otherwise.
 */
export default class extends Controller {
    static targets = ['scheduleInput', 'warning'];

    connect() {
        this.checkFutureDate();
    }

    checkFutureDate() {
        const value = this.scheduleInputTarget.value;
        if (!value) {
            this.warningTarget.classList.add('hidden');
            return;
        }

        const chosen = new Date(value);
        if (Number.isNaN(chosen.getTime()) || chosen.getTime() <= Date.now()) {
            this.warningTarget.classList.add('hidden');
            return;
        }

        const formatted = chosen.toLocaleString('pl-PL', { dateStyle: 'medium', timeStyle: 'short' });
        this.warningTarget.textContent = `Artykuł będzie publicznie dostępny dopiero od ${formatted}.`;
        this.warningTarget.classList.remove('hidden');
    }
}
