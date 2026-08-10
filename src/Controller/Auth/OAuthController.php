<?php

namespace App\Controller\Auth;

use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class OAuthController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google_start')]
    public function connectGoogle(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry->getClient('google')->redirect(['email', 'profile'], []);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectGoogleCheck(
        Request $request,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher
    ): Response {
        return $this->handleOAuth($clientRegistry->getClient('google'), $entityManager, 'google', $request, $eventDispatcher);
    }

    #[Route('/connect/facebook', name: 'connect_facebook_start')]
    public function connectFacebook(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry->getClient('facebook')->redirect(['email', 'public_profile'], []);
    }

    #[Route('/connect/facebook/check', name: 'connect_facebook_check')]
    public function connectFacebookCheck(
        Request $request,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher
    ): Response {
        return $this->handleOAuth($clientRegistry->getClient('facebook'), $entityManager, 'facebook', $request, $eventDispatcher);
    }

    private function handleOAuth(
        $client,
        EntityManagerInterface $entityManager,
        string $provider,
        Request $request,
        EventDispatcherInterface $eventDispatcher
    ): Response {
        try {
            $oauthUser = $client->fetchUser();
            $mail = $oauthUser->getEmail();
            $providerId = $oauthUser->getId();
            $name = $oauthUser->getName();

            $user = $entityManager->getRepository(User::class)->findOneBy([
                $provider.'Id' => $providerId,
            ]);

            if (!$user) {
                $user = $entityManager->getRepository(User::class)->findOneBy(['mail' => $mail]);
                if ($user) {
                    $setter = 'set'.ucfirst($provider).'Id';
                    $user->$setter($providerId);
                } else {
                    $user = new User();
                    $user->setMail($mail);
                    $user->setUsername($mail);
                    $user->setPassword(bin2hex(random_bytes(16)));
                    $setter = 'set'.ucfirst($provider).'Id';
                    $user->$setter($providerId);
                    $user->setFullName($name);
                }
                $entityManager->persist($user);
                $entityManager->flush();
            }

            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->container->get('security.token_storage')->setToken($token);

            $event = new InteractiveLoginEvent($request, $token);
            $eventDispatcher->dispatch($event);

            return $this->redirectToRoute('app_home');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Помилка авторизації: '.$e->getMessage());
            return $this->redirectToRoute('app_login');
        }
    }
}