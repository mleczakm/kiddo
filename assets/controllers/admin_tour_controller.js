import { Controller } from '@hotwired/stimulus';

/**
 * Self-contained spotlight walkthrough of the admin panel.
 * Steps are supplied server-side (translated, per-page target selectors)
 * via the `stepsValue`; any step whose target isn't present on the
 * current page is skipped rather than shown broken.
 */
export default class extends Controller {
    static values = {
        steps: Array,
        storageKey: { type: String, default: 'kiddo_admin_tour_seen' },
        labelNext: String,
        labelBack: String,
        labelSkip: String,
        labelFinish: String,
    };

    #activeSteps = [];
    #index = 0;
    #backdrop = null;
    #highlight = null;
    #tooltip = null;

    connect() {
        if (!localStorage.getItem(this.storageKeyValue)) {
            window.setTimeout(() => this.start(), 600);
        }
    }

    start() {
        this.#activeSteps = this.stepsValue.filter((step) => document.querySelector(`[data-tour-target="${step.target}"]`));
        if (this.#activeSteps.length === 0) {
            return;
        }
        this.#index = 0;
        this.#buildOverlay();
        this.#renderStep();
    }

    next() {
        if (this.#index >= this.#activeSteps.length - 1) {
            this.#finish();
            return;
        }
        this.#index += 1;
        this.#renderStep();
    }

    back() {
        if (this.#index === 0) {
            return;
        }
        this.#index -= 1;
        this.#renderStep();
    }

    skip() {
        this.#finish();
    }

    #finish() {
        localStorage.setItem(this.storageKeyValue, '1');
        this.#teardown();
    }

    #buildOverlay() {
        this.#backdrop = document.createElement('div');
        this.#backdrop.className = 'fixed inset-0 bg-slate-900/60 z-[9999]';

        this.#highlight = document.createElement('div');
        this.#highlight.className = 'fixed z-[10000] rounded-xl ring-4 ring-indigo-500 pointer-events-none transition-all duration-200';
        this.#highlight.style.boxShadow = '0 0 0 9999px rgba(15, 23, 42, 0.6)';

        this.#tooltip = document.createElement('div');
        this.#tooltip.className = 'fixed z-[10001] w-80 max-w-[90vw] bg-white rounded-2xl shadow-2xl border border-slate-200 p-5';

        document.body.append(this.#backdrop, this.#highlight, this.#tooltip);
        document.body.classList.add('overflow-hidden');
    }

    #teardown() {
        this.#backdrop?.remove();
        this.#highlight?.remove();
        this.#tooltip?.remove();
        this.#backdrop = null;
        this.#highlight = null;
        this.#tooltip = null;
        document.body.classList.remove('overflow-hidden');
    }

    #renderStep() {
        const step = this.#activeSteps[this.#index];
        const target = document.querySelector(`[data-tour-target="${step.target}"]`);
        if (!target) {
            this.next();
            return;
        }

        target.scrollIntoView({ block: 'center', behavior: 'smooth' });
        window.setTimeout(() => this.#position(target, step), 250);
    }

    #position(target, step) {
        const rect = target.getBoundingClientRect();
        const padding = 6;

        this.#highlight.style.top = `${rect.top - padding}px`;
        this.#highlight.style.left = `${rect.left - padding}px`;
        this.#highlight.style.width = `${rect.width + padding * 2}px`;
        this.#highlight.style.height = `${rect.height + padding * 2}px`;

        const isFirst = this.#index === 0;
        const isLast = this.#index === this.#activeSteps.length - 1;

        this.#tooltip.innerHTML = `
            <p class="text-xs font-bold text-indigo-600 uppercase tracking-wide mb-2">${step.counter}</p>
            <h3 class="text-base font-black text-slate-800 mb-1.5">${step.title}</h3>
            <p class="text-sm text-slate-600 mb-4">${step.body}</p>
            <div class="flex items-center justify-between gap-2">
                <button type="button" data-tour="skip" class="text-xs font-medium text-slate-400 hover:text-slate-600 transition">${this.labelSkipValue}</button>
                <div class="flex items-center gap-2">
                    ${isFirst ? '' : `<button type="button" data-tour="back" class="px-3 h-8 rounded-lg text-xs font-bold border border-slate-200 text-slate-600 hover:bg-slate-50 transition">${this.labelBackValue}</button>`}
                    <button type="button" data-tour="next" class="px-3 h-8 rounded-lg text-xs font-bold text-white transition" style="background-color: rgb(79, 70, 229);">${isLast ? this.labelFinishValue : this.labelNextValue}</button>
                </div>
            </div>
        `;
        this.#tooltip.querySelector('[data-tour="skip"]').addEventListener('click', () => this.skip());
        this.#tooltip.querySelector('[data-tour="next"]').addEventListener('click', () => this.next());
        this.#tooltip.querySelector('[data-tour="back"]')?.addEventListener('click', () => this.back());

        this.#positionTooltip(rect);
    }

    #positionTooltip(targetRect) {
        const tooltipRect = this.#tooltip.getBoundingClientRect();
        const spacing = 16;

        let top = targetRect.bottom + spacing;
        if (top + tooltipRect.height > window.innerHeight - spacing) {
            top = Math.max(spacing, targetRect.top - tooltipRect.height - spacing);
        }

        let left = targetRect.left;
        if (left + tooltipRect.width > window.innerWidth - spacing) {
            left = window.innerWidth - tooltipRect.width - spacing;
        }
        left = Math.max(spacing, left);

        this.#tooltip.style.top = `${top}px`;
        this.#tooltip.style.left = `${left}px`;
    }
}
