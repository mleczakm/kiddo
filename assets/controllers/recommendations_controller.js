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
        this.closeReader(true);
        this.motion.removeEventListener('change', this.onMotionChange);
        this.resizeObserver.disconnect();
        this.removeClones();
    }

    renderLoop() {
        this.closeReader(true);
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

    read(event) {
        event.preventDefault();
        this.closeReader(true);
        const source = event.currentTarget;
        this.trackTarget.dataset.reading = 'true';
        const rect = source.getBoundingClientRect();
        const background = getComputedStyle(source).backgroundColor;
        const reader = source.cloneNode(true);
        [
            'data-recommendations-target',
            'data-recommendation-clone',
            'data-action',
            'aria-hidden',
            'aria-expanded',
        ].forEach((attribute) => {
            reader.removeAttribute(attribute);
        });
        reader.classList.add('recommendations-reader');
        reader.setAttribute('role', 'region');
        reader.tabIndex = -1;
        reader.dataset.expanded = 'true';
        const width = Math.min(rect.width * 1.18, window.innerWidth - 32);
        Object.assign(reader.style, {
            width: `${width}px`,
            height: 'auto',
            visibility: 'hidden',
            backgroundColor: '#fff',
            backgroundImage: `linear-gradient(${background}, ${background})`,
        });
        document.body.append(reader);
        const height = Math.min(Math.max(rect.height * 1.08, reader.scrollHeight), window.innerHeight - 32);
        const left = Math.max(16, Math.min(rect.left - (width - rect.width) / 2, window.innerWidth - width - 16));
        const top = Math.max(16, Math.min(rect.top - (height - rect.height) / 2, window.innerHeight - height - 16));
        reader.dataset.expanded = 'false';
        Object.assign(reader.style, {
            width: `${rect.width}px`,
            height: `${rect.height}px`,
            left: `${rect.left}px`,
            top: `${rect.top}px`,
            visibility: 'visible',
        });
        source.dataset.readingSource = 'true';
        source.setAttribute('aria-expanded', 'true');
        this.reader = reader;
        this.readingSource = source;
        reader.getBoundingClientRect();
        this.openFrame = requestAnimationFrame(() => {
            reader.dataset.expanded = 'true';
            Object.assign(reader.style, {
                width: `${width}px`,
                height: `${height}px`,
                left: `${left}px`,
                top: `${top}px`,
            });
        });
        reader.addEventListener('keydown', (keyEvent) => {
            if (keyEvent.key === 'Escape') this.closeReader();
        });
        const words = reader.querySelector('.recommendations-full').textContent.trim().split(/\s+/u).length;
        // About 180 words/minute, plus a moment to settle into the enlarged card.
        const readingTime = Math.max(6000, (words / 180) * 60000 + 2500);
        this.readTimer = setTimeout(() => this.closeReader(), readingTime);
        reader.focus({ preventScroll: true });
    }

    closeReader(immediate = false) {
        clearTimeout(this.readTimer);
        clearTimeout(this.closeTimer);
        cancelAnimationFrame(this.openFrame);
        if (!this.reader) return;
        const reader = this.reader;
        const source = this.readingSource;
        const finish = () => {
            reader.remove();
            delete source.dataset.readingSource;
            source.setAttribute('aria-expanded', 'false');
            delete this.trackTarget.dataset.reading;
            this.reader = null;
            this.readingSource = null;
        };
        if (immediate) {
            finish();
            return;
        }
        const rect = source.getBoundingClientRect();
        reader.dataset.expanded = 'false';
        Object.assign(reader.style, {
            width: `${rect.width}px`,
            height: `${rect.height}px`,
            left: `${rect.left}px`,
            top: `${rect.top}px`,
        });
        this.closeTimer = setTimeout(finish, 240);
    }

    appendClones() {
        this.slides.forEach((slide) => {
            const clone = slide.cloneNode(true);
            clone.removeAttribute('data-recommendations-target');
            clone.setAttribute('data-recommendation-clone', '');
            clone.setAttribute('aria-hidden', 'true');
            clone.tabIndex = -1;
            clone.addEventListener('pointerdown', (event) => event.preventDefault());
            this.trackTarget.append(clone);
        });
    }

    removeClones() {
        this.trackTarget.querySelectorAll('[data-recommendation-clone]').forEach((clone) => {
            clone.remove();
        });
    }
}
