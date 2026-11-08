// Service worker for Web Push notifications. Deliberately a plain static
// file (not routed through AssetMapper) so it's reachable at this fixed
// root-scope URL, as `navigator.serviceWorker.register()` requires.

// Take over immediately so a freshly installed app can receive pushes
// without waiting for every old tab to close.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// Chrome on Android only offers "Install app" for workers with a fetch
// handler; this one deliberately does not cache or alter anything.
self.addEventListener('fetch', () => {});

self.addEventListener('push', (event) => {
    let data = {};
    if (event.data) {
        try {
            data = event.data.json();
        } catch {
            data = { title: event.data.text() };
        }
    }

    const title = data.title || 'Kiddo';
    const options = {
        body: data.body || '',
        icon: '/icon-192.png',
        badge: '/icon-192.png',
        tag: data.tag || undefined,
        data: { url: data.url || '/' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = event.notification.data && event.notification.data.url ? event.notification.data.url : '/';
    // Clients report absolute URLs, so resolve the (usually relative) target
    // before comparing, otherwise an open app window is never reused.
    const url = new URL(target, self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }

            return undefined;
        }),
    );
});
