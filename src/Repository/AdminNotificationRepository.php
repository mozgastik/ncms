<?php

namespace App\Repository;

use App\Entity\Notification\AdminNotification;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminNotification>
 */
class AdminNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminNotification::class);
    }

    /**
     * Отримати повідомлення, доступні користувачу.
     */
    public function findForUser(User $user, int $limit = 20): array
{
    $targets = $user->isAdmin()
        ? [
            AdminNotification::TARGET_ALL,
            AdminNotification::TARGET_ADMINS,
        ]
        : [
            AdminNotification::TARGET_ALL,
            AdminNotification::TARGET_USERS,
        ];

    return $this->createQueryBuilder('n')
        ->where('n.target IN (:targets)')
        ->orWhere('n.target = :targetSpecific AND n.user = :user')
        ->orderBy('n.createdAt', 'DESC')
        ->setMaxResults($limit)
        ->setParameter('targets', $targets)
        ->setParameter('targetSpecific', AdminNotification::TARGET_SPECIFIC)
        ->setParameter('user', $user)
        ->getQuery()
        ->getResult();
}

    /**
     * Отримати непрочитані персональні повідомлення користувача.
     *
     * Загальні повідомлення не враховуються, бо readAt зберігається
     * в самому AdminNotification, а не окремо для кожного користувача.
     */
    public function findUnreadByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.target = :targetSpecific')
            ->andWhere('n.user = :user')
            ->andWhere('n.readAt IS NULL')
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('targetSpecific', AdminNotification::TARGET_SPECIFIC)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * Старий alias, якщо десь у коді вже використовується findLatestByUser().
     */
    public function findLatestByUser(User $user, int $limit = 20): array
    {
        return $this->findForUser($user, $limit);
    }

    /**
     * Підрахувати непрочитані персональні повідомлення.
     */
    public function countUnreadByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.target = :targetSpecific')
            ->andWhere('n.user = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('targetSpecific', AdminNotification::TARGET_SPECIFIC)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Позначити всі персональні повідомлення користувача як прочитані.
     */
    public function markAllAsRead(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':now')
            ->where('n.target = :targetSpecific')
            ->andWhere('n.user = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('targetSpecific', AdminNotification::TARGET_SPECIFIC)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Видалити старі повідомлення.
     */
    public function deleteOldNotifications(\DateTimeInterface $before): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}