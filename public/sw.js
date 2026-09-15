// Service worker for Web Push notifications. Deliberately a plain static
// file (not routed through AssetMapper) so it's reachable at this fixed
// root-scope URL, as `navigator.serviceWorker.register()` requires.

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
        icon: '/apple-touch-icon.png',
        badge: '/apple-touch-icon.png',
        data: { url: data.url || '/' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/';

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
