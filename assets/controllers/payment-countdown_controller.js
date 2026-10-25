import { Controller } from '@hotwired/stimulus';

/**
 * Counts down the time left to pay, as MM:SS.
 *
 * `remaining` is the number of seconds left, computed by the server when the
 * markup was rendered (so the visitor's clock doesn't matter). When it reaches
 * zero the `running` target is swapped for the `expired` message.
 */
export default class extends Controller {
    static targets = ['running', 'display', 'expired'];
    static values = { remaining: Number };

    connect() {
        this.start();
    }

    disconnect() {
        this.stop();
    }

    remainingValueChanged() {
        this.start();
    }

    start() {
        this.stop();
        this.deadline = Date.now() + this.remainingValue * 1000;
        this.tick();
        this.timer = window.setInterval(() => this.tick(), 1000);
    }

    stop() {
        if (this.timer) {
            window.clearInterval(this.timer);
            this.timer = null;
        }
    }

    tick() {
        const seconds = Math.max(0, Math.ceil((this.deadline - Date.now()) / 1000));
        const minutes = String(Math.floor(seconds / 60)).padStart(2, '0');

        if (this.hasDisplayTarget) {
            this.displayTarget.textContent = `${minutes}:${String(seconds % 60).padStart(2, '0')}`;
        }

        const expired = seconds === 0;
        if (this.hasRunningTarget) {
            this.runningTarget.hidden = expired;
        }
        if (this.hasExpiredTarget) {
            this.expiredTarget.hidden = !expired;
        }
        if (expired) {
            this.stop();
        }
    }
}
