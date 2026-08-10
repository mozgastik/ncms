<?php
// src/Command/UserPromoteCommand.php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:promote',
    description: 'Надати адмінські права користувачу',
)]
class UserPromoteCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email користувача')
            ->addOption('remove', 'r', null, 'Видалити адмінські права');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $email = $input->getArgument('email');
        $remove = $input->getOption('remove');

        // Знайти користувача
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['mail' => $email]);
        
        if (!$user) {
            $io->error("Користувача з email '{$email}' не знайдено!");
            return Command::FAILURE;
        }

        if ($remove) {
            // Видалити адмінські права
            if ($user->hasRole('ROLE_ADMIN')) {
                $user->removeRole('ROLE_ADMIN');
                $io->success("Адмінські права видалені у користувача: {$email}");
            } else {
                $io->warning("Користувач {$email} не має адмінських прав!");
            }
        } else {
            // Додати адмінські права
            if (!$user->hasRole('ROLE_ADMIN')) {
                $user->addRole('ROLE_ADMIN');
                $io->success("Адмінські права надані користувачу: {$email}");
            } else {
                $io->warning("Користувач {$email} вже має адмінські права!");
            }
        }

        $this->entityManager->flush();

        // Показати інформацію
        $io->table(
            ['Поле', 'Значення'],
            [
                ['Email', $user->getMail()],
                ['Ім\'я', $user->getUsername()],
                ['Ролі', implode(', ', $user->getRoles())],
                ['Адмін?', $user->isAdmin() ? '✅ Так' : '❌ Ні'],
            ]
        );

        return Command::SUCCESS;
    }
}