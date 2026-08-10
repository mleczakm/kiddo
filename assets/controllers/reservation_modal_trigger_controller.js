import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/** Opens the single admin reservation-details LiveComponent from any list. */
export default class extends Controller {
    static values = {
        bookingId: String,
        lessonId: String,
    };

    async open(event) {
        if (event.target.closest('a, button, input, select, textarea') && event.currentTarget !== event.target.closest('button')) {
            return;
        }

        const modalElement = document.querySelector('[data-reservation-details-modal]');
        if (!modalElement) {
            return;
        }

        const component = await getComponent(modalElement);
        await component.action('open', {
            bookingId: this.bookingIdValue,
            lessonId: this.hasLessonIdValue ? this.lessonIdValue : null,
        });
    }
}
