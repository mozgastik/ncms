<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route('/admin/login', name: 'app_admin_login')]
    public function index(AuthenticationUtils $authenticationUtils): Response
    {
        // если пользователь уже авторизован, перенаправляем на дашборд
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_dashboard');
        }

        // получить ошибку входа, если она есть
        $error = $authenticationUtils->getLastAuthenticationError();

        // последнее имя пользователя, введенное пользователем
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('admin/login/index.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    #[Route('/admin/logout', name: 'app_admin_logout')]
    public function logout(): void
    {
        // контроллер может быть пустым: он будет перехвачен ключом выхода в файле конфигурации брандмауэра
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}