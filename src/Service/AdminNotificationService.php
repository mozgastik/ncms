<?php
// src/Service/AdminNotificationService.php

namespace App\Service;

use App\Entity\Notification\AdminNotification;
use App\Entity\User\User;
use App\Repository\AdminNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Notification\NotificationDispatcher;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AdminNotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AdminNotificationRepository $repository,
        private NotifierInterface $notifier,
        private UrlGeneratorInterface $router
    ) {}

    /**
     * Надіслати сповіщення одному користувачу
     */
    public function sendToUser(
        User $user,
        string $title,
        ?string $message = null,
        string $type = 'info',
        ?User $actor = null,
        ?array $data = null
    ): AdminNotification {
        $notification = match($type) {
            'success' => NotificationFactory::success($user, $title, $message, $actor, $data),
            'warning' => NotificationFactory::warning($user, $title, $message, $actor, $data),
            'error' => NotificationFactory::error($user, $title, $message, $actor, $data),
            default => NotificationFactory::info($user, $title, $message, $actor, $data),
        };

        $this->em->persist($notification);
        $this->em->flush();

        // Flash повідомлення для миттєвого зворотнього зв'язку
        $this->notifier->send(new Notification($title, ['browser']));

        return $notification;
    }

    /**
     * Надіслати сповіщення всім адміністраторам
     */
    public function sendToAdmins(
        string $title,
        ?string $message = null,
        string $type = 'info',
        ?User $actor = null,
        ?array $data = null
    ): array {
        $admins = $this->em->getRepository(User::class)->findByRole('ROLE_ADMIN');
        $notifications = [];

        foreach ($admins as $admin) {
            $notifications[] = $this->sendToUser($admin, $title, $message, $type, $actor, $data);
        }

        return $notifications;
    }

    /**
     * Надіслати сповіщення групі користувачів
     */
    public function sendToUsers(
        array $users,
        string $title,
        ?string $message = null,
        string $type = 'info',
        ?User $actor = null,
        ?array $data = null
    ): array {
        $notifications = [];

        foreach ($users as $user) {
            $notifications[] = $this->sendToUser($user, $title, $message, $type, $actor, $data);
        }

        return $notifications;
    }

    /**
     * Позначити як прочитане
     */
    public function markAsRead(AdminNotification $notification): void
    {
        $notification->setReadAt(new \DateTime());
        $this->em->flush();
    }

    /**
     * Позначити всі як прочитані
     */
    public function markAllAsRead(User $user): int
    {
        return $this->repository->markAllAsRead($user);
    }

    /**
     * Отримати кількість непрочитаних
     */
    public function getUnreadCount(User $user): int
    {
        return $this->repository->countUnreadByUser($user);
    }

    /**
     * Очистити старі сповіщення
     */
    public function cleanOldNotifications(int $days = 30): int
    {
        $before = new \DateTime("-{$days} days");
        return $this->repository->deleteOldNotifications($before);
    }

    /**
     * Створити посилання на сутність
     */
    public function createEntityLink(string $entityType, int $entityId): ?string
    {
        return match($entityType) {
            'Article' => $this->router->generate('admin_article_edit', ['id' => $entityId]),
            'Comment' => $this->router->generate('admin_comment_edit', ['id' => $entityId]),
            'User' => $this->router->generate('admin_user_edit', ['id' => $entityId]),
            'Category' => $this->router->generate('admin_category_edit', ['id' => $entityId]),
            'Tag' => $this->router->generate('admin_tag_edit', ['id' => $entityId]),
            default => null,
        };
    }
}