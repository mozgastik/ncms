<?php

namespace App\Service;

use App\Entity\System\Image;
use App\Entity\Article\Article;
use App\Repository\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Filesystem\Filesystem;

class ImageService
{
    private ImageRepository $imageRepository;
    private EntityManagerInterface $entityManager;
    private SluggerInterface $slugger;
    private Filesystem $filesystem;
    private string $uploadDir;  // ← ТІЛЬКИ ОДИН РАЗ
    private string $publicPath;

    public function __construct(
        string $uploadDir,  // ← БЕЗ #[Autowire] і без 'private readonly'
        ImageRepository $imageRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ) {
        $this->uploadDir = $uploadDir;  // ← Присвоюємо
        $this->imageRepository = $imageRepository;
        $this->entityManager = $entityManager;
        $this->slugger = $slugger;
        $this->filesystem = new Filesystem();
        $this->publicPath = '/uploads';
        
        $this->ensureDirectoriesExist();
    }

    /**
     * Створює необхідні директорії
     */
    private function ensureDirectoriesExist(): void
    {
        $directories = [
            $this->uploadDir,
            $this->uploadDir . '/thumbs',
            $this->uploadDir . '/articles',
            $this->uploadDir . '/articles/thumbs',
        ];

        foreach ($directories as $dir) {
            if (!$this->filesystem->exists($dir)) {
                $this->filesystem->mkdir($dir, 0755);
            }
        }
    }

    /**
     * Завантажує зображення
     */
    public function uploadImage(UploadedFile $file, string $subdir = 'articles', ?Article $article = null): array
    {
        if (!$this->isImage($file)) {
            throw new \RuntimeException('Файл не є зображенням');
        }

        if (!$this->isSizeValid($file)) {
            throw new \RuntimeException('Файл занадто великий (макс. 5MB)');
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $filename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $relativePath = $subdir . '/' . $filename;
        $fullPath = $this->uploadDir . '/' . $relativePath;
        $publicPath = $this->publicPath . '/' . $relativePath;

        try {
            $file->move($this->uploadDir . '/' . $subdir, $filename);
            $this->createThumbnail($fullPath, $this->uploadDir . '/thumbs/' . $relativePath);

            $image = new Image();
            $image->setUrl($publicPath);
            $image->setAlt($originalFilename);
            $image->setUploadedAt(new \DateTimeImmutable());
            $image->setPath($relativePath);
            
            if ($article) {
                $image->setArticle($article);
            }

            $this->entityManager->persist($image);
            $this->entityManager->flush();

            return [
                'success' => true,
                'id' => $image->getId(),
                'url' => $publicPath,
                'path' => $relativePath,
                'filename' => $filename,
                'thumbnail' => $this->publicPath . '/thumbs/' . $relativePath,
                'message' => 'Зображення завантажено'
            ];

        } catch (FileException $e) {
            throw new \RuntimeException('Не вдалося завантажити файл: ' . $e->getMessage());
        }
    }

    /**
     * Зберігає зображення для статті
     */
    public function saveImagesForArticle(Article $article, array $imageIds): void
    {
        foreach ($imageIds as $imageId) {
            $image = $this->imageRepository->find($imageId);
            if ($image && !$image->getArticle()) {
                $image->setArticle($article);
                $this->entityManager->persist($image);
            }
        }
        $this->entityManager->flush();
    }

    /**
     * Створює мініатюру
     */
    private function createThumbnail(string $sourcePath, string $targetPath, int $width = 300, int $height = 200): void
    {
        if (!$this->filesystem->exists($sourcePath)) {
            return;
        }

        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return;
        }

        list($srcWidth, $srcHeight, $type) = $imageInfo;

        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($sourcePath);
                break;
            default:
                return;
        }

        if (!$source) {
            return;
        }

        $thumb = imagecreatetruecolor($width, $height);

        if ($type === IMAGETYPE_PNG) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
            imagefilledrectangle($thumb, 0, 0, $width, $height, $transparent);
        }

        $srcAspect = $srcWidth / $srcHeight;
        $thumbAspect = $width / $height;

        if ($srcAspect > $thumbAspect) {
            $srcHeightNew = $srcHeight;
            $srcWidthNew = $srcHeight * $thumbAspect;
            $srcX = ($srcWidth - $srcWidthNew) / 2;
            $srcY = 0;
        } else {
            $srcWidthNew = $srcWidth;
            $srcHeightNew = $srcWidth / $thumbAspect;
            $srcX = 0;
            $srcY = ($srcHeight - $srcHeightNew) / 2;
        }

        imagecopyresampled(
            $thumb, $source,
            0, 0, $srcX, $srcY,
            $width, $height, $srcWidthNew, $srcHeightNew
        );

        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($thumb, $targetPath, 85);
                break;
            case IMAGETYPE_PNG:
                imagepng($thumb, $targetPath, 8);
                break;
            case IMAGETYPE_GIF:
                imagegif($thumb, $targetPath);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($thumb, $targetPath, 85);
                break;
        }

        imagedestroy($source);
        imagedestroy($thumb);
    }

    /**
     * Видаляє зображення
     */
    public function deleteImage(string $path): bool
    {
        $fullPath = $this->uploadDir . '/' . $path;
        $thumbPath = $this->uploadDir . '/thumbs/' . $path;

        $success = true;
        if ($this->filesystem->exists($fullPath)) {
            $success = $success && $this->filesystem->remove($fullPath);
        }
        if ($this->filesystem->exists($thumbPath)) {
            $success = $success && $this->filesystem->remove($thumbPath);
        }

        return $success;
    }

    /**
     * Перевіряє чи є файл зображенням
     */
    public function isImage(UploadedFile $file): bool
    {
        return in_array($file->getMimeType(), [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml'
        ]);
    }

    /**
     * Перевіряє розмір файлу
     */
    public function isSizeValid(UploadedFile $file, int $maxSize = 5242880): bool
    {
        return $file->getSize() <= $maxSize;
    }

    /**
     * Отримує директорію завантаження
     */
    public function getUploadDir(): string
    {
        return $this->uploadDir;
    }
}