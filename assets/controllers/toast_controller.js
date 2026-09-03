import { Controller } from '@hotwired/stimulus';

/**
 * Shared toast/flash renderer for the whole app — mounted once in both the
 * customer frontend (`base.html.twig`) and the admin CRM (`admin/base.html.twig`).
 *
 * Sources of toasts:
 *  - server flash messages, rendered as `data-toast-message` child nodes on
 *    first paint (see `partials/_toasts.html.twig`); `connect()` drains them.
 *  - a bubbling `toast` CustomEvent (`detail: {message, level}`) — dispatched by
 *    plain JS, or by a LiveComponent via `dispatchBrowserEvent('toast', ...)`
 *    (Symfony UX dispatches those with `bubbles: true`, so they reach document).
 */
export default class extends Controller {
    static values = { duration: { type: Number, default: 5000 } };

    connect() {
        this.handler = (event) => {
            const detail = event.detail || {};
            if (detail.message) {
                this.render(String(detail.message), detail.level || 'success');
            }
        };
        document.addEventListener('toast', this.handler);

        this.element
            .querySelectorAll('[data-toast-message]')
            .forEach((node) => {
                this.render(node.getAttribute('data-toast-message'), node.getAttribute('data-toast-level') || 'info');
                node.remove();
            });
    }

    disconnect() {
        document.removeEventListener('toast', this.handler);
    }

    render(message, level) {
        const palette = {
            success: 'bg-emerald-600',
            error: 'bg-red-600',
            warning: 'bg-amber-500',
            info: 'bg-slate-800',
        };

        const toast = document.createElement('div');
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.className =
            `pointer-events-auto flex items-start gap-3 rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg ` +
            `translate-y-2 opacity-0 transition-all duration-300 ${palette[level] || palette.info}`;

        const text = document.createElement('span');
        text.className = 'flex-1';
        text.textContent = message;
        toast.appendChild(text);

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'shrink-0 opacity-70 hover:opacity-100 transition-opacity';
        close.setAttribute('aria-label', 'OK');
        close.textContent = '×';
        close.addEventListener('click', () => this.dismiss(toast));
        toast.appendChild(close);

        this.element.appendChild(toast);
        requestAnimationFrame(() => toast.classList.remove('translate-y-2', 'opacity-0'));

        if (this.durationValue > 0) {
            setTimeout(() => this.dismiss(toast), this.durationValue);
        }
    }

    dismiss(toast) {
        toast.classList.add('translate-y-2', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }
}
