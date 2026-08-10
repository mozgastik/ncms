<?php
// src/Command/SetupQuoteOfTheDayCommand.php

namespace App\Command;

use App\Entity\Quote;
use App\Repository\QuoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:setup-quote-of-the-day',
    description: 'Налаштовує цитати дня на наступний місяць',
)]
class SetupQuoteOfTheDayCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuoteRepository $quoteRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Отримуємо всі активні цитати
        $quotes = $this->quoteRepository->findActiveQuotes();

        if (count($quotes) === 0) {
            $io->error('Немає активних цитат для розподілу!');
            return Command::FAILURE;
        }

        // Починаємо з завтрашнього дня
        $startDate = new \DateTime('tomorrow');
        $endDate = clone $startDate;
        $endDate->modify('+1 month');

        $interval = \DateInterval::createFromDateString('1 day');
        $period = new \DatePeriod($startDate, $interval, $endDate);

        $assigned = 0;
        $quoteIndex = 0;
        $totalQuotes = count($quotes);

        foreach ($period as $date) {
            // Перевіряємо, чи вже є цитата на цю дату
            $existingQuote = $this->quoteRepository->findQuoteOfTheDay($date);
            
            if (!$existingQuote && $totalQuotes > 0) {
                $quote = $quotes[$quoteIndex % $totalQuotes];
                $quote->setDisplayDate($date);
                
                $this->entityManager->persist($quote);
                $assigned++;
                
                $io->writeln(sprintf(
                    'Призначено цитату "%s" на %s',
                    $quote->getShortContent(50),
                    $date->format('Y-m-d')
                ));

                $quoteIndex++;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Успішно призначено %d цитат на наступний місяць!', $assigned));

        return Command::SUCCESS;
    }
}