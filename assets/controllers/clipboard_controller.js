import { Controller } from '@hotwired/stimulus';

/**
 * Copy a string to the clipboard.
 *
 * The trigger button carries the text in `data-clipboard-value`
 * (`data-action="clipboard#copy"`). On success the button label briefly
 * changes to a confirmation, then reverts. Falls back to selecting the
 * `source` target when the async Clipboard API is unavailable.
 */
export default class extends Controller {
    static targets = ['source'];

    async copy(event) {
        const button = event.currentTarget;
        const text = button.dataset.clipboardValue ?? this.sourceTarget?.value ?? '';
        if (!text) {
            return;
        }

        try {
            await navigator.clipboard.writeText(text);
        } catch {
            if (this.hasSourceTarget) {
                this.sourceTarget.focus();
                this.sourceTarget.select();
            }
            return;
        }

        const original = button.textContent;
        button.textContent = button.dataset.clipboardCopiedLabel ?? '✓';
        button.disabled = true;
        window.setTimeout(() => {
            button.textContent = original;
            button.disabled = false;
        }, 1500);
    }
}
