<?php

namespace App\Controller\Auth;

use App\Entity\User\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-password', name: 'app_reset_password_')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    #[Route('', name: 'request')]
    public function request(Request $request, TransportInterface $mailer, UserRepository $userRepository): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mail = $form->get('mail')->getData();
            $user = $userRepository->findOneBy(['mail' => $mail]);

            if ($user) {
                try {
                    $resetToken = $this->resetPasswordHelper->generateResetToken($user);
                    $email = (new TemplatedEmail())
                        ->from('info@adnews.fun')
                        ->to($user->getMail())
                        ->subject('Запит на скидання пароля')
                        ->htmlTemplate('security/reset_password/email.html.twig')
                        ->context([
                            'resetToken' => $resetToken,
                            'user' => $user,
                        ]);

                    $mailer->send($email);
                } catch (ResetPasswordExceptionInterface $e) {
                    // Надто багато запитів або інша помилка бандла
                }
            }

            $this->addFlash('success', 'Якщо обліковий запис існує, лист із посиланням надіслано.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password/request.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/check-email', name: 'check_email')]
    public function checkEmail(): Response
    {
        if (!$this->getTokenFromSession()) {
            return $this->redirectToRoute('app_reset_password_request');
        }

        return $this->render('security/reset_password/check_email.html.twig', [
            'tokenLifetime' => $this->resetPasswordHelper->getTokenLifetime(),
        ]);
    }

    #[Route('/reset/{token}', name: 'reset')]
    public function reset(Request $request, string $token): Response
    {
        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('error', 'Посилання недійсне або термін дії вичерпано.');
            return $this->redirectToRoute('app_reset_password_request');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->resetPasswordHelper->removeResetRequest($token);

            // Хешуємо пароль через впроваджений сервіс
            $hashedPassword = $this->passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );
            $user->setPassword($hashedPassword);
            $this->entityManager->flush();

            $this->addFlash('success', 'Пароль успішно змінено.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password/reset.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}