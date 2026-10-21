import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['slide', 'track', 'viewport'];

    connect() {
        this.removeClones();
        this.slides = [...this.slideTargets];
        const start = Math.floor(Math.random() * this.slides.length);
        this.slides = [...this.slides.slice(start), ...this.slides.slice(0, start)];
        this.slides.forEach((slide) => {
            this.trackTarget.append(slide);
        });
        this.motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.onMotionChange = () => this.renderLoop();
        this.motion.addEventListener('change', this.onMotionChange);
        this.width = this.viewportTarget.clientWidth;
        this.resizeObserver = new ResizeObserver(() => {
            const width = this.viewportTarget.clientWidth;
            if (width !== this.width) {
                this.width = width;
                this.renderLoop();
            }
        });
        this.resizeObserver.observe(this.viewportTarget);
        this.renderLoop();
    }

    disconnect() {
        this.motion.removeEventListener('change', this.onMotionChange);
        this.resizeObserver.disconnect();
        this.removeClones();
    }

    renderLoop() {
        delete this.trackTarget.dataset.looping;
        this.removeClones();
        if (this.motion.matches || this.slides.length < 2) return;

        this.appendClones();
        const firstClone = this.trackTarget.querySelector('[data-recommendation-clone]');
        const distance = firstClone.offsetLeft - this.slides[0].offsetLeft;
        if (!distance) return;

        // Keep enough copies beyond the loop boundary even on very wide screens.
        while (this.trackTarget.scrollWidth < distance + this.viewportTarget.clientWidth) {
            this.appendClones();
        }
        this.trackTarget.style.setProperty('--recommendations-distance', `${distance}px`);
        this.trackTarget.style.setProperty('--recommendations-duration', `${distance / 35}s`);
        this.trackTarget.dataset.looping = 'true';
    }

    appendClones() {
        this.slides.forEach((slide) => {
            const clone = slide.cloneNode(true);
            clone.removeAttribute('data-recommendations-target');
            clone.setAttribute('data-recommendation-clone', '');
            clone.setAttribute('aria-hidden', 'true');
            clone.inert = true;
            this.trackTarget.append(clone);
        });
    }

    removeClones() {
        this.trackTarget.querySelectorAll('[data-recommendation-clone]').forEach((clone) => {
            clone.remove();
        });
    }
}
