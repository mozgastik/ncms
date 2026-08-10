<?php
// src/Controller/User/NotificationSettingsController.php

namespace App\Controller\User;

use App\Entity\Notification\UserNotificationSettings;
use App\Form\UserNotificationSettingsType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile/notifications', name: 'user_notification_settings_')]
#[IsGranted('ROLE_USER')]
class NotificationSettingsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $settings = $user->getNotificationSettings();

        $form = $this->createForm(UserNotificationSettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Налаштування сповіщень збережено');
            return $this->redirectToRoute('user_notification_settings_index');
        }

        return $this->render('user/notification_settings.html.twig', [
            'form' => $form->createView(),
            'settings' => $settings,
        ]);
    }

    #[Route('/disable-all', name: 'disable_all', methods: ['POST'])]
    public function disableAll(): Response
    {
        $user = $this->getUser();
        $settings = $user->getNotificationSettings();

        $settings->setEmailNewArticle(false)
            ->setEmailNewComment(false)
            ->setEmailCommentReply(false)
            ->setEmailWeeklyDigest(false)
            ->setEmailNewsletter(false)
            ->setPushEnabled(false);

        $this->em->flush();

        $this->addFlash('success', 'Всі сповіщення вимкнено');
        return $this->redirectToRoute('user_notification_settings_index');
    }

    #[Route('/enable-essential', name: 'enable_essential', methods: ['POST'])]
    public function enableEssential(): Response
    {
        $user = $this->getUser();
        $settings = $user->getNotificationSettings();

        $settings->setEmailNewArticle(true)
            ->setEmailNewComment(true)
            ->setEmailCommentReply(true)
            ->setEmailWeeklyDigest(false)
            ->setEmailNewsletter(false);

        $this->em->flush();

        $this->addFlash('success', 'Основні сповіщення увімкнено');
        return $this->redirectToRoute('user_notification_settings_index');
    }
}