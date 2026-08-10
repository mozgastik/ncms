<?php

namespace App\Controller\Api;

use App\Entity\Notification\AdminNotification;
use App\Entity\User\User;
use App\Repository\AdminNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications', name: 'api_notifications_')]
#[IsGranted('ROLE_USER')]
class NotificationApiController extends AbstractController
{
    public function __construct(
        private AdminNotificationRepository $notificationRepository,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $limit = max(1, min($request->query->getInt('limit', 20), 100));

        $notifications = $this->notificationRepository->findForUser($user, $limit);
        $unreadCount = $this->notificationRepository->countUnreadByUser($user);

        return $this->json([
            'items' => array_map(
                fn (AdminNotification $notification) => $this->serializeNotification($notification),
                $notifications
            ),
            'total' => count($notifications),
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/{id}/read', name: 'read', methods: ['POST'])]
    public function read(AdminNotification $notification): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$notification->isVisibleFor($user)) {
            throw $this->createNotFoundException();
        }

        if ($notification->getTarget() === AdminNotification::TARGET_SPECIFIC && !$notification->isRead()) {
            $notification->markAsRead();
            $this->em->flush();
        }

        return $this->json([
            'success' => true,
            'unreadCount' => $this->notificationRepository->countUnreadByUser($user),
        ]);
    }

    #[Route('/read-all', name: 'read_all', methods: ['POST'])]
    public function readAll(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->notificationRepository->markAllAsRead($user);

        return $this->json([
            'success' => true,
            'unreadCount' => 0,
        ]);
    }

    private function serializeNotification(AdminNotification $notification): array
    {
        $readAt = $notification->getReadAt();

        if ($notification->getTarget() !== AdminNotification::TARGET_SPECIFIC) {
            $readAt = $notification->getCreatedAt();
        }

        return [
            'id' => $notification->getId(),
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'type' => $notification->getType(),
            'target' => $notification->getTarget(),
            'link' => $notification->getLink(),
            'createdAt' => $notification->getCreatedAt()->format(DATE_ATOM),
            'readAt' => $readAt?->format(DATE_ATOM),
        ];
    }
    
    #[Route('/unread-count', name: 'unread_count', methods: ['GET'])]
   public function unreadCount(): JsonResponse
   {
    /** @var User $user */
    $user = $this->getUser();

    return $this->json([
        'unreadCount' => $this->notificationRepository->countUnreadByUser($user),
    ]);
}
}