<?php

namespace App\Service\Notification;

use App\Entity\Notification\PushSubscription;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;

class PushNotificationService
{
    private WebPush $webPush;
    private bool $enabled;

    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $em,
        ?string $vapidPublicKey = null,
        ?string $vapidPrivateKey = null,
        ?string $vapidSubject = null,
    ) {
        $this->enabled = (bool) ($vapidPublicKey && $vapidPrivateKey && $vapidSubject);

        $auth = [];

        if ($this->enabled) {
            $auth['VAPID'] = [
                'subject' => $vapidSubject,
                'publicKey' => $vapidPublicKey,
                'privateKey' => $vapidPrivateKey,
            ];
        } else {
            $this->logger->warning('VAPID keys not configured. Push notifications are disabled.');
        }

        $this->webPush = new WebPush($auth);
    }

    public function sendToUser(User $user, string $title, ?string $body = null, array $data = []): array
    {
        if (!$this->enabled) {
            return [];
        }

        $settings = $user->getNotificationSettings();

        if (!$settings->isPushEnabled()) {
            return [];
        }

        $results = [];

        foreach ($user->getPushSubscriptions() as $subscription) {
            $results[$subscription->getId()] = $this->safeSend($subscription, $title, $body, $data, $user);
        }

        return $results;
    }

    public function sendToAll(string $title, ?string $body = null, array $data = []): array
    {
        if (!$this->enabled) {
            return [];
        }

        $subscriptions = $this->em
            ->getRepository(PushSubscription::class)
            ->findAll();

        $results = [];

        foreach ($subscriptions as $subscription) {
            $user = $subscription->getUser();

            if ($user && !$user->getNotificationSettings()->isPushEnabled()) {
                $results[$subscription->getId()] = false;
                continue;
            }

            $results[$subscription->getId()] = $this->safeSend($subscription, $title, $body, $data, $user);
        }

        return $results;
    }

    public function sendToSubscription(PushSubscription $subscription, string $title, ?string $body = null, array $data = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $pushSubscription = Subscription::create([
            'endpoint' => $subscription->getEndpoint(),
            'publicKey' => $subscription->getPublicKey(),
            'authToken' => $subscription->getAuthToken(),
            'contentEncoding' => $subscription->getContentEncoding() ?: 'aes128gcm',
        ]);

        $payload = json_encode([
            'title' => $title,
            'message' => $body,
            'body' => $body,
            'url' => $data['url'] ?? '/user/notifications',
            'data' => $data,
            'icon' => '/favicon-32x32.png',
            'badge' => '/favicon-32x32.png',
            'timestamp' => time(),
        ], JSON_THROW_ON_ERROR);

        $this->webPush->queueNotification($pushSubscription, $payload);

        foreach ($this->webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                throw new \RuntimeException('Push failed: ' . $report->getReason());
            }
        }
    }

    private function safeSend(
        PushSubscription $subscription,
        string $title,
        ?string $body,
        array $data,
        ?User $user = null,
    ): bool {
        try {
            $this->sendToSubscription($subscription, $title, $body, $data);

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Push notification failed', [
                'user_id' => $user?->getId(),
                'subscription_id' => $subscription->getId(),
                'error' => $exception->getMessage(),
            ]);

            if ($this->isInvalidSubscription($exception)) {
                $this->em->remove($subscription);
                $this->em->flush();
            }

            return false;
        }
    }

    private function isInvalidSubscription(\Throwable $exception): bool
    {
        return str_contains($exception->getMessage(), 'expired')
            || str_contains($exception->getMessage(), '410')
            || str_contains($exception->getMessage(), '404')
            || str_contains($exception->getMessage(), 'unsubscribed');
    }
}