// public/service-worker.js

self.addEventListener('push', function(event) {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    let data = {};
    
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data = {
                title: 'Нове сповіщення',
                body: event.data.text()
            };
        }
    }

    const options = {
        body: data.body || 'У вас нове сповіщення',
        icon: data.icon || '/build/images/icon-192.png',
        badge: data.badge || '/build/images/badge.png',
        vibrate: [200, 100, 200],
        data: data.data || {},
        actions: data.actions || [
            {
                action: 'open',
                title: 'Відкрити'
            },
            {
                action: 'close',
                title: 'Закрити'
            }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'Нове сповіщення', options)
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    const urlToOpen = event.notification.data?.url || '/';

    if (event.action === 'close') {
        return;
    }

    event.waitUntil(
        clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then(function(clientList) {
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if (client.url === urlToOpen && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});