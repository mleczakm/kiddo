import { Controller } from '@hotwired/stimulus';

/**
 * Enable/disable browser Web Push notifications (OS-level alerts that reach
 * the user even with every tab closed, unlike the polled in-app tray).
 * Backed by `/api/push/subscribe` and `/api/push/unsubscribe`
 * (PushSubscriptionController) plus the service worker at `/sw.js`.
 */
export default class extends Controller {
    static targets = ['status', 'button'];
    static values = { vapidKey: String };

    async connect() {
        // iOS/iPadOS only exposes Web Push to a site launched from the Home
        // Screen, so in a plain Safari tab explain how to install instead of
        // claiming the browser is unsupported.
        if (this.isIos() && !this.isStandalone()) {
            this.setState('ios-install');
            return;
        }

        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !this.vapidKeyValue) {
            this.setState('unsupported');
            return;
        }

        if (Notification.permission === 'denied') {
            this.setState('denied');
            return;
        }

        try {
            const registration = await navigator.serviceWorker.register('/sw.js');
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                // Re-register on every launch: push endpoints can rotate (notably on iOS).
                await this.register(subscription);
            }
            this.setState(subscription ? 'enabled' : 'disabled');
        } catch {
            this.setState('unsupported');
        }
    }

    async toggle() {
        const registration = await navigator.serviceWorker.ready;
        const existing = await registration.pushManager.getSubscription();

        if (existing) {
            await this.unsubscribe(existing);
            return;
        }

        await this.subscribe(registration);
    }

    async subscribe(registration) {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            this.setState(permission === 'denied' ? 'denied' : 'disabled');
            return;
        }

        let subscription;
        try {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.vapidKeyValue),
            });
        } catch {
            this.setState('disabled');
            return;
        }

        if (!(await this.register(subscription))) {
            // Don't keep a browser-side subscription the server doesn't know about.
            await subscription.unsubscribe();
            this.setState('disabled');
            return;
        }

        this.setState('enabled');
    }

    async register(subscription) {
        const keys = subscription.toJSON().keys;
        const response = await fetch('/api/push/subscribe', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                endpoint: subscription.endpoint,
                keys: { p256dh: keys.p256dh, auth: keys.auth },
            }),
        });

        return response.ok;
    }

    isIos() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    }

    isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    }

    async unsubscribe(subscription) {
        const endpoint = subscription.endpoint;
        await subscription.unsubscribe();
        await fetch('/api/push/unsubscribe', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ endpoint }),
        });

        this.setState('disabled');
    }

    setState(state) {
        this.element.dataset.pushState = state;

        if (this.hasStatusTarget) {
            const labels = {
                unsupported: this.statusTarget.dataset.labelUnsupported,
                denied: this.statusTarget.dataset.labelDenied,
                enabled: this.statusTarget.dataset.labelEnabled,
                disabled: this.statusTarget.dataset.labelDisabled,
                'ios-install': this.statusTarget.dataset.labelIosInstall,
            };
            this.statusTarget.textContent = labels[state] ?? '';
        }

        if (this.hasButtonTarget) {
            this.buttonTarget.hidden = state === 'unsupported' || state === 'denied' || state === 'ios-install';
            this.buttonTarget.textContent =
                state === 'enabled' ? this.buttonTarget.dataset.labelDisable : this.buttonTarget.dataset.labelEnable;
        }
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);

        return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
    }
}
