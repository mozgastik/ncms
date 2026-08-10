<?php

namespace App\Service\Notification;

use App\Entity\Notification\AdminNotification;
use App\Entity\User\User;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;

class NotificationDispatcher
{
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_PUSH = 'push';

    public function __construct(
        private EmailNotificationService $emailService,
        private PushNotificationService $pushService,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Відправити сповіщення всіма доступними каналами.
     */
    public function dispatch(
        AdminNotification $notification,
        array $channels = [self::CHANNEL_EMAIL, self::CHANNEL_PUSH],
    ): array {
        $results = [];

        foreach ($channels as $channel) {
            try {
                $results[$channel] = match ($channel) {
                    self::CHANNEL_EMAIL => $this->dispatchEmail($notification),
                    self::CHANNEL_PUSH => $this->dispatchPush($notification),
                    default => false,
                };
            } catch (\Throwable $exception) {
                $this->logger->error('Failed to dispatch notification', [
                    'notification_id' => $notification->getId(),
                    'channel' => $channel,
                    'error' => $exception->getMessage(),
                ]);

                $results[$channel] = false;
            }
        }

        return $results;
    }

    /**
     * Відправити сповіщення конкретному користувачу.
     */
    public function dispatchToUser(
        User $user,
        AdminNotification $notification,
        array $channels = [self::CHANNEL_EMAIL, self::CHANNEL_PUSH],
    ): array {
        $settings = $user->getNotificationSettings();
        $results = [];

        foreach ($channels as $channel) {
            try {
                $results[$channel] = match ($channel) {
                    self::CHANNEL_EMAIL => $settings->canSendEmail($notification->getAction())
                        ? $this->emailService->sendToUser($user, $notification)
                        : false,
                    self::CHANNEL_PUSH => $settings->canSendPush($notification->getAction())
                        ? $this->pushService->sendToUser(
                            $user,
                            $notification->getTitle(),
                            $notification->getMessage(),
                        )
                        : false,
                    default => false,
                };
            } catch (\Throwable $exception) {
                $this->logger->error('Failed to dispatch notification to user', [
                    'user_id' => $user->getId(),
                    'notification_id' => $notification->getId(),
                    'channel' => $channel,
                    'error' => $exception->getMessage(),
                ]);

                $results[$channel] = false;
            }
        }

        return $results;
    }

    /**
     * Відправити сповіщення всім адміністраторам.
     */
    public function dispatchToAdmins(
        Notification $notification,
        array $channels = [self::CHANNEL_EMAIL, self::CHANNEL_PUSH],
    ): array {
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');
        $results = [];

        foreach ($admins as $admin) {
            $results[$admin->getId()] = $this->dispatchToUser($admin, $notification, $channels);
        }

        return $results;
    }

    private function dispatchEmail(AdminNotification $notification): bool
    {
        // Логіка для масової email-розсилки.
        return true;
    }

    private function dispatchPush(AdminNotification $notification): array
    {
        return $this->pushService->sendToAll(
            $notification->getTitle(),
            $notification->getMessage(),
            ['url' => $notification->getLink()],
        );
    }
}