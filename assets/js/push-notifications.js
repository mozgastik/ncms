// assets/js/push-notifications.js

class PushNotificationManager {
    constructor() {
        this.swUrl = '/service-worker.js';
        this.publicKey = null;
        this.registration = null;
    }

    async init() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.log('Push notifications not supported');
            return false;
        }

        try {
            // Отримати публічний ключ
            const response = await fetch('/push/vapid-public-key');
            const data = await response.json();
            this.publicKey = this.urlBase64ToUint8Array(data.publicKey);

            // Зареєструвати service worker
            this.registration = await navigator.serviceWorker.register(this.swUrl);
            
            console.log('Service Worker registered');
            return true;
        } catch (error) {
            console.error('Push initialization failed:', error);
            return false;
        }
    }

    async subscribe() {
        if (!this.registration) {
            await this.init();
        }

        try {
            // Підписатися на push
            const subscription = await this.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.publicKey
            });

            // Відправити підписку на сервер
            const response = await fetch('/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(subscription)
            });

            const result = await response.json();
            console.log('Subscribed to push notifications:', result);
            
            return result;
        } catch (error) {
            console.error('Subscription failed:', error);
            throw error;
        }
    }

    async unsubscribe() {
        if (!this.registration) {
            return;
        }

        const subscription = await this.registration.pushManager.getSubscription();
        
        if (subscription) {
            // Відписатися на клієнті
            await subscription.unsubscribe();

            // Повідомити сервер
            await fetch('/push/unsubscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    endpoint: subscription.endpoint
                })
            });

            console.log('Unsubscribed from push notifications');
        }
    }

    async getSubscriptionStatus() {
        if (!this.registration) {
            await this.init();
        }

        const subscription = await this.registration.pushManager.getSubscription();
        return !!subscription;
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
}

// Експорт для використання
export default PushNotificationManager;