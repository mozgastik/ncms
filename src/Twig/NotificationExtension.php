<?php
// src/Twig/NotificationExtension.php

namespace App\Twig;

use App\Repository\AdminNotificationRepository;
use App\Service\AdminNotificationService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private AdminNotificationService $notificationService,
        private AdminNotificationRepository $notificationRepository,
        private Security $security
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('notification_unread_count', [$this, 'getUnreadCount']),
            new TwigFunction('notification_latest', [$this, 'getLatestNotifications']),
            new TwigFunction('notification_format_date', [$this, 'formatDate']),
            new TwigFunction('notification_icon_class', [$this, 'getIconClass']),
            new TwigFunction('notification_bg_class', [$this, 'getBackgroundClass']),
        ];
    }

    /**
     * Кількість непрочитаних сповіщень для поточного або заданого користувача.
     */
    public function getUnreadCount(?UserInterface $user = null): int
    {
        $user = $user ?? $this->security->getUser();
        if (!$user) {
            return 0;
        }

        return $this->notificationService->getUnreadCount($user);
    }

    /**
     * Останні сповіщення (наприклад, для дзвіночка).
     * @return array
     */
    public function getLatestNotifications(int $limit = 5, ?UserInterface $user = null): array
    {
        $user = $user ?? $this->security->getUser();
        if (!$user) {
            return [];
        }

        return $this->notificationRepository->findLatestByUser($user, $limit);
    }

    public function formatDate(\DateTimeInterface $date): string
    {
        $now = new \DateTime();
        $diff = $now->diff($date);

        if ($diff->days > 30) {
            return $date->format('d.m.Y');
        }
        if ($diff->days > 0) {
            return $diff->days . ' ' . $this->pluralize($diff->days, 'день', 'дні', 'днів');
        }
        if ($diff->h > 0) {
            return $diff->h . ' ' . $this->pluralize($diff->h, 'година', 'години', 'годин');
        }
        if ($diff->i > 0) {
            return $diff->i . ' ' . $this->pluralize($diff->i, 'хвилина', 'хвилини', 'хвилин');
        }
        return 'щойно';
    }

    public function getIconClass(string $type): string
    {
        return match($type) {
            'success' => 'fa-check-circle text-(--color-success-600) dark:text-(--color-success-400)',
            'warning' => 'fa-exclamation-triangle text-(--color-warning-600) dark:text-(--color-warning-400)',
            'error' => 'fa-times-circle text-(--color-danger-600) dark:text-(--color-danger-400)',
            default => 'fa-info-circle text-(--color-primary-600) dark:text-(--color-primary-400)',
        };
    }

    public function getBackgroundClass(string $type): string
    {
        return match($type) {
            'success' => 'bg-(--color-success-100) dark:bg-(--color-success-900)/30',
            'warning' => 'bg-(--color-warning-100) dark:bg-(--color-warning-900)/30',
            'error' => 'bg-(--color-danger-100) dark:bg-(--color-danger-900)/30',
            default => 'bg-(--color-primary-100) dark:bg-(--color-primary-900)/30',
        };
    }

    private function pluralize(int $number, string $one, string $few, string $many): string
    {
        $mod10 = $number % 10;
        $mod100 = $number % 100;

        if ($mod100 >= 11 && $mod100 <= 19) {
            return $many;
        }
        if ($mod10 == 1) {
            return $one;
        }
        if ($mod10 >= 2 && $mod10 <= 4) {
            return $few;
        }
        return $many;
    }
}