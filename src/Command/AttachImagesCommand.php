<?php

namespace App\Command;

use App\Entity\Article\Article;
use App\Entity\System\Image;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:attach-images',
    description: 'Attach existing images to articles'
)]
class AttachImagesCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Attaching Images to Articles');

        // Очищаємо проблемні поля
        $io->section('Cleaning problematic fields...');
        $this->cleanProblematicFields($io);

        // Прив'язуємо зображення
        $io->section('Attaching images...');
        $this->attachImages($io);

        $io->success('All done!');

        return Command::SUCCESS;
    }

    private function cleanProblematicFields(SymfonyStyle $io): void
    {
        $conn = $this->entityManager->getConnection();
        
        try {
            // Очищаємо meta_description
            $conn->executeStatement('UPDATE articles SET meta_description = NULL');
            $io->writeln('✅ Cleared meta_description');
            
        } catch (\Exception $e) {
            $io->error('Error cleaning fields: ' . $e->getMessage());
        }
    }

    private function attachImages(SymfonyStyle $io): void
    {
        $projectDir = $this->getApplication()->getKernel()->getProjectDir();
        $uploadDir = $projectDir . '/public/uploads/articles/';

        // Отримуємо всі файли з директорії
        $files = [];
        if (is_dir($uploadDir)) {
            $scanned = scandir($uploadDir);
            foreach ($scanned as $file) {
                if (!in_array($file, ['.', '..']) && !is_dir($uploadDir . $file)) {
                    $files[] = $file;
                }
            }
        }

        $io->info(sprintf('Found %d image files in uploads/articles/', count($files)));

        if (empty($files)) {
            $io->warning('No files found in uploads/articles/ directory!');
            return;
        }

        // Отримуємо всі статті
        $articles = $this->entityManager->getRepository(Article::class)->findAll();
        $io->info(sprintf('Found %d articles', count($articles)));

        $attached = 0;
        $updated = 0;
        $usedImages = [];

        // Мапа: ID статті -> назва файлу
        $imageMap = $this->getImageMap();

        foreach ($articles as $article) {
            $articleId = $article->getId();
            $io->writeln(sprintf('Processing article #%d: %s', $articleId, $article->getTitle()));

            // Перевіряємо чи вже є зображення в базі
            $existingImages = $this->entityManager->getRepository(Image::class)
                ->findBy(['article' => $article]);

            if (count($existingImages) > 0) {
                $io->writeln('  ⏭️ Already has images in database');
                continue;
            }

            // Шукаємо відповідне зображення
            $foundImage = null;

            // Спроба 1: За ID статті в назві файлу
            foreach ($files as $file) {
                if (strpos($file, (string)$articleId) !== false && !in_array($file, $usedImages)) {
                    $foundImage = $file;
                    break;
                }
            }

            // Спроба 2: З мапи
            if (!$foundImage && isset($imageMap[$articleId])) {
                $mappedImage = $imageMap[$articleId];
                foreach ($files as $file) {
                    if ($file === $mappedImage && !in_array($file, $usedImages)) {
                        $foundImage = $file;
                        break;
                    }
                }
            }

            // Спроба 3: За частиною заголовку
            if (!$foundImage) {
                $searchTitle = $this->getSearchableTitle($article->getTitle());
                foreach ($files as $file) {
                    $fileLower = strtolower($file);
                    if (strpos($fileLower, $searchTitle) !== false && !in_array($file, $usedImages)) {
                        $foundImage = $file;
                        break;
                    }
                }
            }

            // Спроба 4: Беремо перше доступне зображення
            if (!$foundImage) {
                foreach ($files as $file) {
                    if (!in_array($file, $usedImages)) {
                        $foundImage = $file;
                        break;
                    }
                }
            }

            if ($foundImage) {
                $io->writeln(sprintf('  ✅ Found image: %s', $foundImage));
                
                try {
                    // Створюємо запис в таблиці images
                    $image = new Image();
                    $image->setImageName($foundImage);
                    $image->setUrl('/uploads/articles/' . $foundImage);
                    $image->setPath('uploads/articles/' . $foundImage);
                    $image->setArticle($article);
                    $image->setIsCover(true);
                    $image->setIsFeatured(true);
                    $image->setType('article');
                    
                    // Безпечне встановлення title та alt
                    $safeTitle = $this->sanitizeText($article->getTitle());
                    $image->setTitle($safeTitle);
                    $image->setAlt($safeTitle);
                    
                    // Отримуємо інформацію про файл
                    $filePath = $uploadDir . $foundImage;
                    if (file_exists($filePath)) {
                        $image->setImageSize(filesize($filePath));
                        $image->setImageMimeType(mime_content_type($filePath));
                        
                        $imageInfo = getimagesize($filePath);
                        if ($imageInfo) {
                            $image->setImageDimensions([
                                'width' => $imageInfo[0],
                                'height' => $imageInfo[1],
                            ]);
                        }
                    }

                    // Оновлюємо cover_image в Article
                    if (!$article->getCoverImage()) {
                        $article->setCoverImage($foundImage);
                        $updated++;
                    }

                    $this->entityManager->persist($image);
                    $usedImages[] = $foundImage;
                    $attached++;
                    
                    $io->writeln('  ✅ Image attached successfully');
                    
                } catch (\Exception $e) {
                    $io->error(sprintf('  ❌ Error: %s', $e->getMessage()));
                }
            } else {
                $io->writeln('  ❌ No image found for this article');
            }
        }

        // Зберігаємо всі зміни
        try {
            $this->entityManager->flush();
            $io->success('All changes saved successfully!');
        } catch (\Exception $e) {
            $io->error('Error saving: ' . $e->getMessage());
            
            // Спроба зберегти окремо
            $io->writeln('Trying to save individually...');
            foreach ($articles as $article) {
                try {
                    $this->entityManager->persist($article);
                    $this->entityManager->flush();
                } catch (\Exception $e) {
                    $io->writeln(sprintf('  ❌ Error saving article #%d: %s', $article->getId(), $e->getMessage()));
                }
            }
        }

        // Виводимо результати
        $io->section('Results');
        $io->table(
            ['Status', 'Count'],
            [
                ['Images attached', $attached],
                ['Articles updated with cover_image', $updated],
                ['Total files found', count($files)],
                ['Images used', count($usedImages)],
                ['Images remaining', count($files) - count($usedImages)],
            ]
        );

        // Показуємо список використаних та невикористаних файлів
        $unusedFiles = array_diff($files, $usedImages);
        if (!empty($unusedFiles)) {
            $io->section('Unused files');
            $io->listing(array_slice($unusedFiles, 0, 10));
            if (count($unusedFiles) > 10) {
                $io->writeln(sprintf('... and %d more', count($unusedFiles) - 10));
            }
        }
    }

    private function getImageMap(): array
    {
        // Мапа: ID статті -> назва файлу
        return [
            1 => '6a5657c28a65d027120427.webp',
            2 => '6a56725f18316_250px-Autistic-sweetiepie-boy-with-ducksinarow.jpg',
            5 => '6a56726007e6b_lw2zcu---c1280x646x0sx12s--0b33fb222c9a8710c78fcaa4ec3d628e.jpg',
            18 => '6a567261956cd_ba5e17bfd05bafb1bd5857a94ff18f3a.jpeg',
            19 => '6a58cce003c49920436190.webp',
            21 => '69b6aaf3a67d4.webp',
            22 => '6a5672b03bb80_lw2zcu---c1280x646x0sx12s--0b33fb222c9a8710c78fcaa4ec3d628e.jpg',
            26 => '6a5672602f7a7_sjkfm75_p6_big.thumb.jpg.f4ef9efebfcf9a281d063b714f0e9547.jpg',
            27 => '6a5a0d0e9b990166352704.webp',
            28 => '6a56726163a14_2ee54dbe-4284-4460-9a35-048e62d739bd_w650_r0_s.jpg',
        ];
    }

    private function getSearchableTitle(?string $title): string
    {
        if (!$title) {
            return '';
        }

        $translit = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'h','ґ'=>'g','д'=>'d','е'=>'e','є'=>'ye',
            'ж'=>'zh','з'=>'z','и'=>'y','і'=>'i','ї'=>'yi','й'=>'y','к'=>'k','л'=>'l',
            'м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
            'ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ю'=>'yu','я'=>'ya'
        ];
        
        $searchable = strtolower($title);
        $searchable = strtr($searchable, $translit);
        $searchable = preg_replace('/[^a-z0-9-]/', '', $searchable);
        $searchable = trim($searchable, '-');
        $searchable = substr($searchable, 0, 30);
        
        return $searchable;
    }

    private function sanitizeText(?string $text): string
    {
        if (!$text) {
            return 'Image';
        }

        // Видаляємо всі небезпечні символи
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text ?: 'Image';
    }
}