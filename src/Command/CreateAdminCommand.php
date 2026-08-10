<?php

namespace App\Command;

use App\Entity\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Создание администратора',
)]
class CreateAdminCommand extends Command
{
    private $entityManager;
    private $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Эта команда создает администратора для панели управления')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        $output->writeln([
            'Создание администратора',
            '=======================',
            '',
        ]);

        // Email
        $emailQuestion = new Question('Введите email администратора: ', 'admin@news.ua');
        $email = $helper->ask($input, $output, $emailQuestion);

        // Проверка существования пользователя
        $existingUser = $this->entityManager->getRepository(AdminUser::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $output->writeln('<error>Пользователь с таким email уже существует!</error>');
            return Command::FAILURE;
        }

        // Полное имя
        $nameQuestion = new Question('Введите полное имя: ', 'Адміністратор');
        $fullName = $helper->ask($input, $output, $nameQuestion);

        // Пароль
        $passwordQuestion = new Question('Введите пароль: ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);
        $password = $helper->ask($input, $output, $passwordQuestion);

        // Подтверждение пароля
        $confirmQuestion = new Question('Подтвердите пароль: ');
        $confirmQuestion->setHidden(true);
        $confirmQuestion->setHiddenFallback(false);
        $confirmPassword = $helper->ask($input, $output, $confirmQuestion);

        if ($password !== $confirmPassword) {
            $output->writeln('<error>Пароли не совпадают!</error>');
            return Command::FAILURE;
        }

        // Создание пользователя
        $user = new AdminUser();
        $user->setEmail($email);
        $user->setFullName($fullName);
        $user->setRoles(['ROLE_ADMIN']);
        
        // Хэширование пароля
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln('<info>Администратор успешно создан!</info>');
        $output->writeln('');
        $output->writeln('Данные для входа:');
        $output->writeln('Email: ' . $email);
        $output->writeln('Пароль: [скрыт]');

        return Command::SUCCESS;
    }
}