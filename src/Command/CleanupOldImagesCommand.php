<?php
// src/Command/CleanupOldImagesCommand.php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'app:cleanup-old-images',
    description: 'Очищення старих зображень після міграції',
)]
class CleanupOldImagesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(param: 'kernel.project_dir')] 
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Тільки показати що буде видалено')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Видалити без підтвердження')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        $io->title('🧹 Очищення старих зображень');

        // Отримуємо всі поточні зображення з БД
        $currentImages = $this->getCurrentImages();
        $oldFiles = $this->findOldFiles($currentImages);

        if (empty($oldFiles)) {
            $io->success('✅ Не знайдено старих файлів для видалення');
            return Command::SUCCESS;
        }

        $io->writeln("Знайдено " . count($oldFiles) . " старих файлів");

        if (!$force && !$dryRun) {
            if (!$io->confirm('Видалити старі файли?', false)) {
                $io->warning('Операція скасована');
                return Command::SUCCESS;
            }
        }

        $fs = new Filesystem();
        $deleted = 0;

        $progress = $io->createProgressBar(count($oldFiles));
        $progress->start();

        foreach ($oldFiles as $file) {
            if (!$dryRun) {
                $fs->remove($file);
                $deleted++;
            }
            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);

        if ($dryRun) {
            $io->warning('🧪 DRY RUN: Без змін');
            $io->writeln("Буде видалено: {$deleted} файлів");
        } else {
            $io->success("✅ Видалено {$deleted} файлів");
        }

        return Command::SUCCESS;
    }

    private function getCurrentImages(): array
    {
        $images = [];

        // Обкладинки статей
        $coverImages = $this->entityManager
            ->createQuery('SELECT a.coverImage FROM App\Entity\Article a WHERE a.coverImage IS NOT NULL AND a.coverImage != \'\'')
            ->getSingleColumnResult();
        $images = array_merge($images, $coverImages);

        // Аватари користувачів
        $avatars = $this->entityManager
            ->createQuery('SELECT u.avatar FROM App\Entity\User u WHERE u.avatar IS NOT NULL AND u.avatar != \'\'')
            ->getSingleColumnResult();
        $images = array_merge($images, $avatars);

        // Зображення галереї (якщо є)
        $galleryImages = $this->entityManager
            ->createQuery('SELECT i.imageName FROM App\Entity\ArticleImage i WHERE i.imageName IS NOT NULL AND i.imageName != \'\'')
            ->getSingleColumnResult();
        $images = array_merge($images, $galleryImages);

        return array_unique($images);
    }

    private function findOldFiles(array $currentImages): array
    {
        $oldFiles = [];
        $publicDir = $this->projectDir . '/public';
        
        $directories = [
            $publicDir . '/uploads/articles',
            $publicDir . '/uploads/avatars',
            $publicDir . '/uploads/articles_old',
            $publicDir . '/uploads/avatars_old',
            $publicDir . '/uploads/gallery',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                // Перевіряємо чи використовується файл
                $isUsed = false;
                foreach ($currentImages as $image) {
                    if ($image === $file) {
                        $isUsed = true;
                        break;
                    }
                }

                if (!$isUsed) {
                    $oldFiles[] = $dir . '/' . $file;
                }
            }
        }

        return $oldFiles;
    }
}