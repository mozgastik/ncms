<?php
// src/Service/Notification/EmailNotificationService.php

namespace App\Service\Notification;

use App\Entity\User\User;
use App\Entity\Notification\AdminNotification;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class EmailNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger,
        private string $senderEmail = 'noreply@your-site.com',
        private string $senderName = 'Новинний портал'
    ) {}

    /**
     * Надіслати email одному користувачу
     */
    public function sendToUser(User $user, AdminNotification $notification): void
    {
        try {
            $email = (new Email())
                ->from(new Address($this->senderEmail, $this->senderName))
                ->to(new Address($user->getEmail(), $user->getUsername()))
                ->subject($notification->getTitle())
                ->html($this->renderTemplate($notification, $user));

            $this->mailer->send($email);
            
            $this->logger->info('Email sent to user', [
                'user_id' => $user->getId(),
                'notification_id' => $notification->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Масова розсилка всім користувачам
     */
    public function sendBulkToAll(array $users, AdminNotification $notification, int $batchSize = 50): array
    {
        $results = [
            'total' => count($users),
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];

        $batches = array_chunk($users, $batchSize);
        
        foreach ($batches as $batch) {
            foreach ($batch as $user) {
                try {
                    $this->sendToUser($user, $notification);
                    $results['sent']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'user_id' => $user->getId(),
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // Затримка між батчами, щоб не перевантажити сервер
            if (count($batches) > 1) {
                sleep(1);
            }
        }

        return $results;
    }

    /**
     * Масова розсилка за ролями
     */
    public function sendBulkByRole(string $role, AdminNotification $notification): array
    {
        $users = $this->getUsersByRole($role);
        return $this->sendBulkToAll($users, $notification);
    }

    /**
     * Асинхронна масова розсилка (через Messenger)
     */
    public function sendBulkAsync(array $users, AdminNotification $notification): void
    {
        // Використовуйте Symfony Messenger для асинхронної обробки
        // Потрібно створити Message та Handler
    }

    private function renderTemplate(AdminNotification $notification, User $user): string
    {
        return $this->twig->render('email/notification.html.twig', [
            'notification' => $notification,
            'user' => $user,
            'site_name' => 'Новинний портал'
        ]);
    }

    private function getUsersByRole(string $role): array
    {
        // Отримати користувачів за роллю через репозиторій
        // return $this->userRepository->findByRole($role);
        return [];
    }
}