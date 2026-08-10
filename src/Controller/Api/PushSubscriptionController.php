<?php

namespace App\Controller\Api;

use App\Entity\Notification\PushSubscription;
use App\Entity\User\User;
use App\Service\Notification\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/push', name: 'api_push_')]
#[IsGranted('ROLE_USER')]
class PushSubscriptionController extends AbstractController
{
    #[Route('/subscribe', name: 'subscribe', methods: ['POST'])]
    public function subscribe(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!$data || empty($data['endpoint']) || empty($data['keys']['p256dh']) || empty($data['keys']['auth'])) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid subscription',
            ], 400);
        }

        $subscription = $em->getRepository(PushSubscription::class)->findOneBy([
            'endpoint' => $data['endpoint'],
        ]);

        if (!$subscription) {
            $subscription = new PushSubscription();
            $subscription->setEndpoint($data['endpoint']);
            $em->persist($subscription);
        }

        $subscription
            ->setUser($user)
            ->setPublicKey($data['keys']['p256dh'])
            ->setAuthToken($data['keys']['auth'])
            ->setContentEncoding($data['contentEncoding'] ?? 'aes128gcm')
            ->setUserAgent($request->headers->get('User-Agent'))
            ->touch();

        $user->getNotificationSettings()->setPushEnabled(true);

        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/unsubscribe', name: 'unsubscribe', methods: ['POST'])]
    public function unsubscribe(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (empty($data['endpoint'])) {
            return $this->json([
                'success' => false,
                'error' => 'Endpoint is required',
            ], 400);
        }

        $subscription = $em->getRepository(PushSubscription::class)->findOneBy([
            'user' => $user,
            'endpoint' => $data['endpoint'],
        ]);

        if ($subscription) {
            $em->remove($subscription);
            $em->flush();
        }

        return $this->json(['success' => true]);
    }

    #[Route('/test', name: 'test', methods: ['POST'])]
    public function test(PushNotificationService $pushService): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $result = $pushService->sendToUser(
            $user,
            'Тестове push-сповіщення',
            'Якщо ви бачите це повідомлення, push працює.',
            ['url' => '/user/notifications']
        );

        if (!$result) {
            return $this->json([
                'success' => false,
                'error' => 'Немає активних push-підписок або push вимкнено.',
            ], 400);
        }

        return $this->json(['success' => true]);
    }
    #[Route('/public-key', name: 'public_key', methods: ['GET'])]
    public function publicKey(string $vapidPublicKey): JsonResponse
    {
        return $this->json([
            'publicKey' => $vapidPublicKey,
        ]);
    }
}