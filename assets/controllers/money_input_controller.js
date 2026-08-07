import { Controller } from '@hotwired/stimulus';

/**
 * Keeps a money input readable while typing: strips anything that isn't a
 * digit or a decimal separator, treats the last comma/dot as the decimal
 * point (earlier ones are assumed to be thousands separators), and caps the
 * fraction at two digits. Mirrors App\Application\Service\MoneyInputParser,
 * which does the authoritative parsing server-side.
 */
export default class extends Controller {
    connect() {
        this.element.value = this.clean(this.element.value);
    }

    sanitize() {
        const field = this.element;
        const cursorFromEnd = field.value.length - field.selectionStart;
        const cleaned = this.clean(field.value);

        if (cleaned === field.value) {
            return;
        }

        field.value = cleaned;
        const position = Math.max(0, cleaned.length - cursorFromEnd);
        field.setSelectionRange(position, position);
    }

    clean(raw) {
        const value = raw.replace(/[^\d,.]/g, '');
        const lastSeparator = Math.max(value.lastIndexOf(','), value.lastIndexOf('.'));

        if (lastSeparator === -1) {
            return value;
        }

        const integerPart = value.slice(0, lastSeparator).replace(/[,.]/g, '');
        const fractionPart = value
            .slice(lastSeparator + 1)
            .replace(/[^\d]/g, '')
            .slice(0, 2);

        return `${integerPart},${fractionPart}`;
    }
}
