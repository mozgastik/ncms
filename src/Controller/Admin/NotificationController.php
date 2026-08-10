<?php
// src/Controller/Admin/NotificationController.php

namespace App\Controller\Admin;

use App\Entity\Notification\AdminNotification;
use App\Form\AdminNotificationType;
use App\Repository\AdminNotificationRepository;
use App\Service\AdminNotificationService;
use App\Service\Notification\NotificationDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/notifications')]
#[IsGranted('ROLE_ADMIN')]
class NotificationController extends AbstractController
{
    public function __construct(
        private AdminNotificationRepository $repository,
        private AdminNotificationService $service,
        private EntityManagerInterface $em,
        private NotificationDispatcher $dispatcher
    ) {}

    #[Route('', name: 'admin_notification_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $notifications = $this->repository->findLatestByUser($user, 50);
        $unreadCount = $this->service->getUnreadCount($user);

        return $this->render('admin/notification/index.html.twig', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

     #[Route('/create', name: 'admin_notification_create', methods: ['GET', 'POST'])]
public function create(Request $request): Response
{
    $notification = new AdminNotification();

    /** @var User|null $user */
    $user = $this->getUser();

    if ($user instanceof User) {
        $notification->setActor($user);
    }

    $form = $this->createForm(AdminNotificationType::class, $notification);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        if ($notification->getTarget() === AdminNotification::TARGET_SPECIFIC && !$notification->getUser()) {
            $this->addFlash('error', 'Оберіть користувача для персонального сповіщення.');

            return $this->render('admin/notification/create.html.twig', [
                'form' => $form,
            ]);
        }

        if ($notification->getTarget() !== AdminNotification::TARGET_SPECIFIC) {
            $notification->setUser(null);
        }

        $this->em->persist($notification);
        $this->em->flush();

        $this->dispatcher->dispatch($notification);

        $this->addFlash('success', 'Сповіщення створено та надіслано');

        return $this->redirectToRoute('admin_notification_index');
    }

    return $this->render('admin/notification/create.html.twig', [
        'form' => $form,
    ]);
}

    #[Route('/edit/{id}', name: 'admin_notification_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, AdminNotification $notification): Response
    {
        $form = $this->createForm(AdminNotificationType::class, $notification);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Сповіщення оновлено');
            return $this->redirectToRoute('admin_notification_index');
        }

        return $this->render('admin/notification/edit.html.twig', [
            'form' => $form,
            'notification' => $notification,
        ]);
    }

    #[Route('/delete/{id}', name: 'admin_notification_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, AdminNotification $notification): Response
    {
        if ($this->isCsrfTokenValid('delete'.$notification->getId(), $request->request->get('_token'))) {
            $this->em->remove($notification);
            $this->em->flush();
            $this->addFlash('success', 'Сповіщення видалено');
        } else {
            $this->addFlash('error', 'Невірний CSRF токен');
        }

        return $this->redirectToRoute('admin_notification_index');
    }

    #[Route('/mark-read/{id}', name: 'admin_notification_mark_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markAsRead(AdminNotification $notification): Response
    {
        $notification->setRead(true);
        $this->em->flush();

        return $this->redirectToRoute('admin_notification_index');
    }

    #[Route('/mark-all-read', name: 'admin_notification_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(): Response
    {
        $this->service->markAllAsRead($this->getUser());
        $this->addFlash('success', 'Усі сповіщення позначено прочитаними');
        return $this->redirectToRoute('admin_notification_index');
    }

    #[Route('/send', name: 'admin_notification_send', methods: ['POST'])]
    public function send(Request $request): Response
    {
        $id = $request->request->get('id');
        $notification = $this->repository->find($id);
        if (!$notification) {
            $this->addFlash('error', 'Сповіщення не знайдено');
            return $this->redirectToRoute('admin_notification_index');
        }

        $results = $this->dispatcher->dispatch($notification);
        $success = in_array(true, $results, true);
        $this->addFlash($success ? 'success' : 'error', $success ? 'Розсилку виконано' : 'Не вдалося надіслати');
        return $this->redirectToRoute('admin_notification_index');
    }

    #[Route('/settings', name: 'admin_notification_settings')]
    public function settings(): Response
    {
        return $this->render('admin/notification/settings.html.twig');
    }
}