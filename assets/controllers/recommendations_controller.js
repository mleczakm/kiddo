import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['slide', 'controls', 'position'];

    connect() {
        this.index = Math.floor(Math.random() * this.slideTargets.length);
        if (this.hasControlsTarget) this.controlsTarget.hidden = false;
        this.show();
    }

    previous(event) {
        event?.preventDefault();
        this.move(-1);
    }

    next(event) {
        event?.preventDefault();
        this.move(1);
    }

    move(direction) {
        const count = this.slideTargets.length;
        if (!count) return;
        this.index = (this.index + direction + count) % count;
        this.show();
    }

    show() {
        this.slideTargets.forEach((slide, index) => {
            slide.hidden = index !== this.index;
        });
        if (this.hasPositionTarget) {
            this.positionTarget.textContent = `${this.index + 1} / ${this.slideTargets.length}`;
        }
    }
}
