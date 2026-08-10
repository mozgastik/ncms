<?php
// src/Service/Notification/TelegramNotificationService.php

namespace App\Service\Notification;

use App\Entity\User;
use App\Entity\AdminNotification;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Transport\Telegram\TelegramOptions;
use Psr\Log\LoggerInterface;

class TelegramNotificationService
{
    public function __construct(
        private ChatterInterface $chatter,
        private LoggerInterface $logger,
    ) {}

    public function sendToChannel(AdminNotification $notification): bool
    {
        try {
            $message = $this->formatMessage($notification);
            
            $telegramOptions = (new TelegramOptions())
                ->chatId($this->channelId)
                ->parseMode('HTML')
                ->disableWebPagePreview(true);

            $chatMessage = (new ChatMessage($message))
                ->options($telegramOptions);

            $this->chatter->send($chatMessage);
            
            $this->logger->info('Telegram message sent to channel');
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Telegram send failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Відправити особисте повідомлення користувачу
     */
    public function sendToUser(User $user, AdminNotification $notification): bool
    {
        if (!$user->getTelegramId()) {
            $this->logger->warning('User has no Telegram ID', [
                'user_id' => $user->getId()
            ]);
            return false;
        }

        try {
            $message = $this->formatMessage($notification);
            
            $telegramOptions = (new TelegramOptions())
                ->chatId($user->getTelegramId())
                ->parseMode('HTML');

            $chatMessage = (new ChatMessage($message))
                ->options($telegramOptions);

            $this->chatter->send($chatMessage);
            
            $this->logger->info('Telegram message sent to user', [
                'user_id' => $user->getId()
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Telegram send to user failed', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Відправити повідомлення всім адміністраторам
     */
    public function sendToAdmins(AdminNotification $notification): array
    {
        $admins = $this->getAdminsWithTelegram();
        $results = [];

        foreach ($admins as $admin) {
            $results[$admin->getId()] = $this->sendToUser($admin, $notification);
        }

        return $results;
    }

    private function formatMessage(AdminNotification $notification): string
    {
        $emoji = match($notification->getType()) {
            'success' => '✅',
            'warning' => '⚠️',
            'error' => '❌',
            default => 'ℹ️'
        };

        $message = "<b>{$emoji} {$notification->getTitle()}</b>\n\n";

        if ($notification->getMessage()) {
            $message .= htmlspecialchars($notification->getMessage()) . "\n\n";
        }

        if ($notification->getLink()) {
            $message .= "<a href='{$notification->getLink()}'>🔗 Детальніше</a>\n";
        }

        if ($notification->getData()) {
            $message .= "\n<b>Деталі:</b>\n";
            foreach ($notification->getData() as $key => $value) {
                $message .= "• {$key}: " . htmlspecialchars($value) . "\n";
            }
        }

        return $message;
    }

    private function getAdminsWithTelegram(): array
    {
        // Отримати адмінів з Telegram ID
        // return $this->userRepository->findAdminsWithTelegram();
        return [];
    }
}