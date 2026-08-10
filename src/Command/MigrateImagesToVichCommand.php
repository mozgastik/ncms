<?php

namespace App\Command;

use App\Entity\Article\Article;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:migrate-images-to-vich',
    description: 'Міграція існуючих зображень до VichUploader',
)]
class MigrateImagesToVichCommand extends Command
{
    private SymfonyStyle $io;
    private array $stats = [
        'articles' => 0,
        'users' => 0,
        'images' => 0,
        'errors' => 0,
        'downloaded' => 0,
        'skipped' => 0,
    ];
    private HttpClientInterface $httpClient;

    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(param: 'kernel.project_dir')] 
        private string $projectDir,
    ) {
        parent::__construct();
        $this->httpClient = HttpClient::create();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Тільки показати що буде зроблено')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Примусово виконати міграцію без підтвердження')
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'Мігрувати тільки конкретну сутність (article, user)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Обмежити кількість записів для міграції')
            ->addOption('download-urls', null, InputOption::VALUE_NONE, 'Спробувати завантажити зображення з URL')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');
        $entity = $input->getOption('entity');
        $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $downloadUrls = $input->getOption('download-urls');

        $this->io->title('🔄 Міграція зображень до VichUploader');

        if ($dryRun) {
            $this->io->warning('🧪 DRY RUN режим - зміни не будуть застосовані');
        }

        if ($downloadUrls) {
            $this->io->note('📥 Буде виконано спробу завантаження зображень з URL');
        }

        $this->checkDirectories();
        $this->showStats();

        if (!$force && !$dryRun) {
            if (!$this->io->confirm('Продовжити міграцію? Це може зайняти деякий час.', false)) {
                $this->io->warning('Операція скасована');
                return Command::SUCCESS;
            }
        }

        if (!$entity || $entity === 'article') {
            $this->migrateArticleImages($dryRun, $limit, $downloadUrls);
        }

        if (!$entity || $entity === 'user') {
            $this->migrateUserAvatars($dryRun, $limit, $downloadUrls);
        }

        $this->showResults();

        return Command::SUCCESS;
    }

    private function checkDirectories(): void
    {
        $fs = new Filesystem();
        $publicDir = $this->projectDir . '/public_html';
        
        $directories = [
            $publicDir . '/uploads/articles',
            $publicDir . '/uploads/articles_old',
            $publicDir . '/uploads/avatars',
            $publicDir . '/uploads/avatars_old',
            $publicDir . '/uploads/temp', // Для тимчасового зберігання завантажених файлів
        ];

        foreach ($directories as $dir) {
            if (!$fs->exists($dir)) {
                $fs->mkdir($dir, 0755);
                $this->io->note("Створено директорію: {$dir}");
            }
        }
    }

    private function showStats(): void
    {
        $this->io->section('📊 Поточна статистика');

        $articleCount = $this->entityManager
            ->createQuery('SELECT COUNT(a) FROM App\Entity\Article\Article a WHERE a.coverImage IS NOT NULL AND a.coverImage != \'\'')
            ->getSingleScalarResult();

        $this->io->writeln("Статей з обкладинками: {$articleCount}");

        $userCount = $this->entityManager
            ->createQuery('SELECT COUNT(u) FROM App\Entity\User\User u WHERE u.avatar IS NOT NULL AND u.avatar != \'\'')
            ->getSingleScalarResult();

        $this->io->writeln("Користувачів з аватарами: {$userCount}");
    }

    private function isUrl(string $path): bool
    {
        return filter_var($path, FILTER_VALIDATE_URL) !== false;
    }

    private function downloadImage(string $url, string $targetPath): bool
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 30,
                'max_redirects' => 5,
            ]);

            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();
                file_put_contents($targetPath, $content);
                return true;
            }
        } catch (\Exception $e) {
            $this->io->error("Помилка завантаження: {$e->getMessage()}");
        }

        return false;
    }

    private function getFileNameFromUrl(string $url): string
    {
        // Отримуємо ім'я файлу з URL
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';
        $filename = basename($path);
        
        // Якщо немає розширення - додаємо
        if (!pathinfo($filename, PATHINFO_EXTENSION)) {
            $filename .= '.jpg';
        }
        
        // Очищаємо ім'я
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // Додаємо унікальний префікс
        return uniqid() . '_' . $filename;
    }

    private function migrateArticleImages(bool $dryRun, ?int $limit, bool $downloadUrls): void
    {
        $this->io->section('📸 Міграція зображень статей');

        $query = $this->entityManager
            ->createQuery('SELECT a FROM App\Entity\Article\Article a WHERE a.coverImage IS NOT NULL AND a.coverImage != \'\'')
            ->setMaxResults($limit);

        $articles = $query->getResult();
        $total = count($articles);

        $this->io->writeln("Знайдено {$total} статей з обкладинками");

        if ($total === 0) {
            $this->io->writeln('✅ Немає зображень для міграції');
            return;
        }

        $progress = $this->io->createProgressBar($total);
        $progress->start();

        $publicDir = $this->projectDir . '/public_html';

        foreach ($articles as $article) {
            $oldImage = $article->getCoverImage();
            
            if (!$oldImage) {
                $progress->advance();
                continue;
            }

            $isUrl = $this->isUrl($oldImage);
            $fileFound = false;

            if ($isUrl) {
                // Це URL
                if ($downloadUrls) {
                    $this->io->text("\n📥 Завантаження: " . substr($oldImage, 0, 80) . '...');
                    
                    $filename = $this->getFileNameFromUrl($oldImage);
                    $targetPath = $publicDir . '/uploads/articles/' . $filename;
                    
                    if ($this->downloadImage($oldImage, $targetPath)) {
                        $fileFound = true;
                        $this->stats['downloaded']++;
                        
                        if (!$dryRun) {
                            $article->setCoverImage($filename);
                            $this->entityManager->persist($article);
                            $this->stats['articles']++;
                        }
                    }
                } else {
                    $this->io->text("\n⏭️ Пропущено URL: " . substr($oldImage, 0, 60) . '...');
                    $this->stats['skipped']++;
                }
            } else {
                // Локальний файл
                $oldPath = $publicDir . '/uploads/articles/' . $oldImage;
                $newPath = $publicDir . '/uploads/articles/' . $oldImage;

                if (file_exists($oldPath)) {
                    $fileFound = true;
                    if (!$dryRun) {
                        $article->setCoverImage($oldImage);
                        $this->entityManager->persist($article);
                        $this->stats['articles']++;
                    }
                }
            }

            if ($fileFound) {
                $this->stats['images']++;
            } else {
                $this->stats['errors']++;
                $this->io->error("Файл не знайдено: {$oldImage}");
            }

            $progress->advance();
        }

        $progress->finish();
        $this->io->newLine(2);

        if (!$dryRun) {
            $this->entityManager->flush();
            $this->io->writeln('✅ Зміни збережено в базі даних');
        }
    }

    private function migrateUserAvatars(bool $dryRun, ?int $limit, bool $downloadUrls): void
    {
        $this->io->section('👤 Міграція аватарів користувачів');

        $query = $this->entityManager
            ->createQuery('SELECT u FROM App\Entity\User\User u WHERE u.avatar IS NOT NULL AND u.avatar != \'\'')
            ->setMaxResults($limit);

        $users = $query->getResult();
        $total = count($users);

        $this->io->writeln("Знайдено {$total} користувачів з аватарами");

        if ($total === 0) {
            $this->io->writeln('✅ Немає аватарів для міграції');
            return;
        }

        $progress = $this->io->createProgressBar($total);
        $progress->start();

        $publicDir = $this->projectDir . '/public_html';

        foreach ($users as $user) {
            $oldAvatar = $user->getAvatar();
            
            if (!$oldAvatar) {
                $progress->advance();
                continue;
            }

            $isUrl = $this->isUrl($oldAvatar);
            $fileFound = false;

            if ($isUrl) {
                if ($downloadUrls) {
                    $this->io->text("\n📥 Завантаження аватара: " . substr($oldAvatar, 0, 60) . '...');
                    
                    $filename = $this->getFileNameFromUrl($oldAvatar);
                    $targetPath = $publicDir . '/uploads/avatars/' . $filename;
                    
                    if ($this->downloadImage($oldAvatar, $targetPath)) {
                        $fileFound = true;
                        $this->stats['downloaded']++;
                        
                        if (!$dryRun) {
                            $user->setAvatar($filename);
                            $this->entityManager->persist($user);
                            $this->stats['users']++;
                        }
                    }
                } else {
                    $this->stats['skipped']++;
                }
            } else {
                $oldPath = $publicDir . '/uploads/avatars/' . $oldAvatar;
                $newPath = $publicDir . '/uploads/avatars/' . $oldAvatar;

                if (file_exists($oldPath)) {
                    $fileFound = true;
                    if (!$dryRun) {
                        $user->setAvatar($oldAvatar);
                        $this->entityManager->persist($user);
                        $this->stats['users']++;
                    }
                }
            }

            if ($fileFound) {
                $this->stats['images']++;
            } else {
                $this->stats['errors']++;
                $this->io->error("Файл не знайдено: {$oldAvatar}");
            }

            $progress->advance();
        }

        $progress->finish();
        $this->io->newLine(2);

        if (!$dryRun) {
            $this->entityManager->flush();
            $this->io->writeln('✅ Зміни збережено в базі даних');
        }
    }

    private function showResults(): void
    {
        $this->io->section('📊 Результати міграції');

        $rows = [
            ['Обкладинки статей', $this->stats['articles']],
            ['Аватари користувачів', $this->stats['users']],
            ['Завантажено з URL', $this->stats['downloaded']],
            ['Пропущено (URL)', $this->stats['skipped']],
            ['Всього зображень', $this->stats['images']],
            ['Помилок', $this->stats['errors']],
        ];

        $this->io->table(['Тип', 'Кількість'], $rows);

        if ($this->stats['errors'] > 0) {
            $this->io->warning("⚠️ {$this->stats['errors']} помилок під час міграції");
        } else {
            $this->io->success('✅ Міграція завершена успішно!');
        }

        if ($this->stats['skipped'] > 0) {
            $this->io->note([
                "📌 {$this->stats['skipped']} URL пропущено. Для завантаження URL використовуйте опцію --download-urls",
            ]);
        }

        $this->io->note([
            '📌 Перевірте зображення на сайті',
            '📌 Для завантаження зовнішніх URL виконайте: php bin/console app:migrate-images-to-vich --download-urls',
        ]);
    }
}