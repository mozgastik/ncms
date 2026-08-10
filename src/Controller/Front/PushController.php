<?php
// src/Controller/PushController.php

namespace App\Controller\Front;

use App\Entity\Notification\PushSubscription;
use App\Entity\User\User;
use App\Service\Notification\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/push')]
#[IsGranted('ROLE_USER')]
class PushController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PushNotificationService $pushService
    ) {}

    #[Route('/subscribe', name: 'push_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'])) {
            return $this->json(['error' => 'Invalid subscription data'], 400);
        }

        $user = $this->getUser();

        // Перевірити чи вже існує така підписка
        $existing = $this->em->getRepository(PushSubscription::class)
            ->findOneBy(['endpoint' => $data['endpoint']]);

        if ($existing) {
            return $this->json(['message' => 'Already subscribed']);
        }

        $subscription = new PushSubscription();
        $subscription->setUser($user);
        $subscription->setEndpoint($data['endpoint']);
        $subscription->setPublicKey($data['keys']['p256dh']);
        $subscription->setAuthToken($data['keys']['auth']);
        $subscription->setUserAgent($request->headers->get('User-Agent'));

        $this->em->persist($subscription);
        $this->em->flush();

        return $this->json(['message' => 'Subscribed successfully']);
    }

    #[Route('/unsubscribe', name: 'push_unsubscribe', methods: ['POST'])]
    public function unsubscribe(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $subscription = $this->em->getRepository(PushSubscription::class)
            ->findOneBy(['endpoint' => $data['endpoint']]);

        if ($subscription) {
            $this->em->remove($subscription);
            $this->em->flush();
        }

        return $this->json(['message' => 'Unsubscribed successfully']);
    }

    #[Route('/vapid-public-key', name: 'push_public_key', methods: ['GET'])]
    public function getPublicKey(): JsonResponse
    {
        return $this->json([
            'publicKey' => $_ENV['VAPID_PUBLIC_KEY'] ?? ''
        ]);
    }

    #[Route('/test', name: 'push_test', methods: ['POST'])]
    public function testPush(): JsonResponse
    {
        $user = $this->getUser();
        
        $results = $this->pushService->sendToUser(
            $user,
            'Тестове сповіщення',
            'Якщо ви це бачите, push-сповіщення працюють!',
            ['url' => $this->generateUrl('admin_dashboard')]
        );

        return $this->json($results);
    }
}