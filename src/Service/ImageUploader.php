<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;

class ImageUploader
{
    private string $targetDirectory;
    private SluggerInterface $slugger;

    public function __construct(
        string $targetDirectory,
        SluggerInterface $slugger
    ) {
        $this->targetDirectory = $targetDirectory;
        $this->slugger = $slugger;
        
        // Створюємо директорію, якщо її немає
        if (!file_exists($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }
        
        // Створюємо піддиректорію для мініатюр
        $thumbDirectory = $targetDirectory . '/thumbs';
        if (!file_exists($thumbDirectory)) {
            mkdir($thumbDirectory, 0755, true);
        }
    }

    /**
     * Завантажує зображення та повертає ім'я файлу
     */
    public function upload(UploadedFile $file): string
    {
        // Отримуємо оригінальне ім'я без розширення
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        // Створюємо безпечне ім'я файлу
        $safeFilename = $this->slugger->slug($originalFilename);
        
        // Генеруємо унікальне ім'я файлу
        $fileName = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            // Переміщуємо файл в цільову директорію
            $file->move($this->getTargetDirectory(), $fileName);
            
            // Створюємо мініатюру
            $this->createThumbnail($fileName);
            
            return $fileName;
        } catch (FileException $e) {
            throw new \RuntimeException('Не вдалося завантажити файл: ' . $e->getMessage());
        }
    }

    /**
     * Створює мініатюру зображення
     */
    private function createThumbnail(string $filename): void
    {
        $filePath = $this->getTargetDirectory() . '/' . $filename;
        $thumbPath = $this->getTargetDirectory() . '/thumbs/' . $filename;
        
        // Перевіряємо чи існує оригінальний файл
        if (!file_exists($filePath)) {
            return;
        }
        
        // Отримуємо інформацію про зображення
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            return;
        }
        
        $mimeType = $imageInfo['mime'];
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        // Визначаємо тип зображення
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($filePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($filePath);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($filePath);
                break;
            default:
                return; // Не підтримуваний формат
        }
        
        if (!$sourceImage) {
            return;
        }
        
        // Розміри мініатюри
        $thumbWidth = 300;
        $thumbHeight = 200;
        
        // Створюємо нове зображення для мініатюри
        $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);
        
        // Додаємо прозорий фон для PNG
        if ($mimeType === 'image/png') {
            imagealphablending($thumbImage, false);
            imagesavealpha($thumbImage, true);
            $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
            imagefilledrectangle($thumbImage, 0, 0, $thumbWidth, $thumbHeight, $transparent);
        }
        
        // Масштабуємо з обрізкою (cover)
        $srcAspect = $width / $height;
        $thumbAspect = $thumbWidth / $thumbHeight;
        
        if ($srcAspect > $thumbAspect) {
            // Ширина джерела більша
            $srcHeight = $height;
            $srcWidth = $height * $thumbAspect;
            $srcX = ($width - $srcWidth) / 2;
            $srcY = 0;
        } else {
            // Висота джерела більша
            $srcWidth = $width;
            $srcHeight = $width / $thumbAspect;
            $srcX = 0;
            $srcY = ($height - $srcHeight) / 2;
        }
        
        // Копіюємо та масштабуємо
        imagecopyresampled(
            $thumbImage, $sourceImage,
            0, 0, $srcX, $srcY,
            $thumbWidth, $thumbHeight, $srcWidth, $srcHeight
        );
        
        // Зберігаємо мініатюру
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($thumbImage, $thumbPath, 85);
                break;
            case 'image/png':
                imagepng($thumbImage, $thumbPath, 8);
                break;
            case 'image/gif':
                imagegif($thumbImage, $thumbPath);
                break;
            case 'image/webp':
                imagewebp($thumbImage, $thumbPath, 85);
                break;
        }
        
        // Очищуємо пам'ять
        imagedestroy($sourceImage);
        imagedestroy($thumbImage);
    }

    /**
     * Видаляє зображення та його мініатюру
     */
    public function delete(string $filename): bool
    {
        $success = true;
        
        // Шлях до оригінального файлу
        $filePath = $this->getTargetDirectory() . '/' . $filename;
        if (file_exists($filePath)) {
            $success = $success && unlink($filePath);
        }
        
        // Шлях до мініатюри
        $thumbPath = $this->getTargetDirectory() . '/thumbs/' . $filename;
        if (file_exists($thumbPath)) {
            $success = $success && unlink($thumbPath);
        }
        
        return $success;
    }

    /**
     * Отримує URL для зображення
     */
    public function getImageUrl(string $filename): string
    {
        return '/uploads/blogs/' . $filename;
    }

    /**
     * Отримує URL для мініатюри
     */
    public function getThumbnailUrl(string $filename): string
    {
        return '/uploads/blogs/thumbs/' . $filename;
    }

    /**
     * Перевіряє чи є файл зображенням
     */
    public function isImage(UploadedFile $file): bool
    {
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png', 
            'image/gif',
            'image/webp',
            'image/svg+xml'
        ];
        
        return in_array($file->getMimeType(), $allowedMimeTypes);
    }

    /**
     * Перевіряє розмір файлу
     */
    public function isSizeValid(UploadedFile $file, int $maxSize = 5242880): bool
    {
        return $file->getSize() <= $maxSize;
    }

    /**
     * Отримує інформацію про файл
     */
    public function getFileInfo(string $filename): ?array
    {
        $filePath = $this->getTargetDirectory() . '/' . $filename;
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        $imageInfo = getimagesize($filePath);
        
        if (!$imageInfo) {
            return null;
        }
        
        return [
            'filename' => $filename,
            'path' => $filePath,
            'url' => $this->getImageUrl($filename),
            'thumbnail_url' => $this->getThumbnailUrl($filename),
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'mime_type' => $imageInfo['mime'],
            'size' => filesize($filePath),
            'created_at' => filemtime($filePath),
        ];
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }
}