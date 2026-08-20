import { Controller } from '@hotwired/stimulus';

/**
 * Warns when the "planned publication" datetime is in the future, since
 * clicking "Save & publish" then schedules rather than publishing
 * immediately — easy to miss otherwise.
 */
export default class extends Controller {
    static targets = ['scheduleInput', 'warning', 'publishButton'];

    connect() {
        this.checkFutureDate();
    }

    checkFutureDate() {
        const value = this.scheduleInputTarget.value;
        const isFuture = value !== '' && !Number.isNaN(new Date(value).getTime()) && new Date(value).getTime() > Date.now();

        if (this.hasPublishButtonTarget) {
            const { labelPublish, labelSchedule } = this.publishButtonTarget.dataset;
            this.publishButtonTarget.textContent = isFuture ? labelSchedule : labelPublish;
        }

        if (!isFuture) {
            this.warningTarget.classList.add('hidden');
            return;
        }

        const formatted = new Date(value).toLocaleString('pl-PL', { dateStyle: 'medium', timeStyle: 'short' });
        this.warningTarget.textContent = `Artykuł będzie publicznie dostępny dopiero od ${formatted}.`;
        this.warningTarget.classList.remove('hidden');
    }
}
