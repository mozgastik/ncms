<?php
// src/Service/NotificationFactory.php

namespace App\Service;

use App\Entity\Notification\AdminNotification;
use App\Entity\User\User;

class NotificationFactory
{
    /**
     * Інформаційне повідомлення
     */
    public static function info(
        User $user,
        string $title,
        ?string $message = null,
        ?User $actor = null,
        ?array $data = null
    ): AdminNotification {
        return self::create($user, $title, $message, 'info', 'fa-info-circle', $actor, $data);
    }

    /**
     * Повідомлення про успіх
     */
    public static function success(
        User $user,
        string $title,
        ?string $message = null,
        ?User $actor = null,
        ?array $data = null
    ): AdminNotification {
        return self::create($user, $title, $message, 'success', 'fa-check-circle', $actor, $data);
    }

    /**
     * Попередження
     */
    public static function warning(
        User $user,
        string $title,
        ?string $message = null,
        ?User $actor = null,
        ?array $data = null
    ): AdminNotification {
        return self::create($user, $title, $message, 'warning', 'fa-exclamation-triangle', $actor, $data);
    }

    /**
     * Помилка
     */
    public static function error(
        User $user,
        string $title,
        ?string $message = null,
        ?User $actor = null,
        ?array $data = null
    ): AdminNotification {
        return self::create($user, $title, $message, 'error', 'fa-times-circle', $actor, $data);
    }

    private static function create(
        User $user,
        string $title,
        ?string $message,
        string $type,
        string $icon,
        ?User $actor,
        ?array $data
    ): AdminNotification {
        $notification = new AdminNotification();
        $notification->setUser($user);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setType($type);
        $notification->setIcon($icon);
        $notification->setActor($actor);
        $notification->setData($data);

        if ($data && isset($data['action'])) {
            $notification->setAction($data['action']);
        }
        if ($data && isset($data['entity_type'])) {
            $notification->setEntityType($data['entity_type']);
        }
        if ($data && isset($data['entity_id'])) {
            $notification->setEntityId($data['entity_id']);
        }
        if ($data && isset($data['link'])) {
            $notification->setLink($data['link']);
        }

        return $notification;
    }
}