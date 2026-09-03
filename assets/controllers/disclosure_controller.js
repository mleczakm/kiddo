import { Controller } from '@hotwired/stimulus';

/**
 * Collapsible disclosure.
 *
 * A trigger (`data-action="disclosure#toggle"`) shows/hides every `content`
 * target. Used to keep inactive (cancelled / rescheduled) reservations behind
 * a "N inactive reservations" toggle. Works with block elements and with
 * multiple <tr> content targets inside a table.
 */
export default class extends Controller {
    static targets = ['content', 'icon'];
    static values = { open: { type: Boolean, default: false } };

    connect() {
        this.sync();
    }

    toggle() {
        this.openValue = !this.openValue;
    }

    openValueChanged() {
        this.sync();
    }

    sync() {
        this.contentTargets.forEach((el) => {
            el.hidden = !this.openValue;
        });
        this.iconTargets.forEach((el) => {
            el.classList.toggle('rotate-90', this.openValue);
        });
        this.element.querySelectorAll('[data-disclosure-trigger]').forEach((el) => {
            el.setAttribute('aria-expanded', this.openValue ? 'true' : 'false');
        });
    }
}
