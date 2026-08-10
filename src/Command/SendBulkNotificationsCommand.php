<?php
// src/Command/SendBulkNotificationsCommand.php

namespace App\Command;

use App\Service\Notification\EmailNotificationService;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notifications:send-bulk',
    description: 'Масова розсилка email-сповіщень',
)]
class SendBulkNotificationsCommand extends Command
{
    public function __construct(
        private EmailNotificationService $emailService,
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('role', 'r', InputOption::VALUE_OPTIONAL, 'Роль користувачів')
            ->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Заголовок')
            ->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Повідомлення')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Тип', 'info')
            ->addOption('batch', 'b', InputOption::VALUE_OPTIONAL, 'Розмір батчу', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $role = $input->getOption('role');
        $title = $input->getOption('title');
        $message = $input->getOption('message');
        $type = $input->getOption('type');
        $batchSize = (int) $input->getOption('batch');

        $io->title('Масова розсилка сповіщень');
        $io->text([
            "Заголовок: $title",
            "Тип: $type",
            "Роль: " . ($role ?: 'всі користувачі'),
            "Розмір батчу: $batchSize"
        ]);

        // Отримуємо користувачів
        $users = $role 
            ? $this->userRepository->findByRole($role)
            : $this->userRepository->findAll();

        $io->text(sprintf('Знайдено %d користувачів', count($users)));

        if (!$io->confirm('Продовжити розсилку?')) {
            $io->warning('Операцію скасовано');
            return Command::SUCCESS;
        }

        // Створюємо сповіщення
        $notification = new AdminNotification();
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setType($type);

        // Відправляємо
        $io->progressStart(count($users));
        
        $results = $this->emailService->sendBulkToAll($users, $notification, $batchSize);
        
        $io->progressFinish();

        $io->success([
            'Розсилку завершено',
            "Відправлено: {$results['sent']}",
            "Помилок: {$results['failed']}"
        ]);

        return Command::SUCCESS;
    }
}