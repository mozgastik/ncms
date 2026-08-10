<?php
// src/Command/CleanNotificationsCommand.php

namespace App\Command;

use App\Service\AdminNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notifications:clean',
    description: 'Очищення старих сповіщень',
)]
class CleanNotificationsCommand extends Command
{
    public function __construct(
        private AdminNotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Кількість днів', 30)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Виконати без підтвердження');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        
        $io->title('Очищення старих сповіщень');
        $io->text(sprintf('Будуть видалені сповіщення старші %d днів', $days));

        if (!$input->getOption('force')) {
            $confirm = $io->confirm('Продовжити?', false);
            if (!$confirm) {
                $io->warning('Операцію скасовано');
                return Command::SUCCESS;
            }
        }

        $deleted = $this->notificationService->cleanOldNotifications($days);
        
        $io->success(sprintf('Видалено %d сповіщень', $deleted));
        
        return Command::SUCCESS;
    }
}