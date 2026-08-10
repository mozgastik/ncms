<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Psr\Log\LoggerInterface;

class FileUploader
{
    private string $uploadDirectory;
    private string $publicPath;
    private SluggerInterface $slugger;
    private LoggerInterface $logger;
    private array $allowedMimeTypes;
    private int $maxFileSize;
    private bool $createThumbnails;
    private array $thumbnailSizes;

    public function __construct(
        string $uploadDirectory,
        string $publicPath,
        SluggerInterface $slugger,
        LoggerInterface $logger,
        array $allowedMimeTypes = [
            'image/jpeg',
            'image/png', 
            'image/gif',
            'image/webp',
            'image/svg+xml'
        ],
        int $maxFileSize = 5242880, // 5MB
        bool $createThumbnails = true,
        array $thumbnailSizes = [
            'small' => [150, 150],
            'medium' => [300, 300],
            'large' => [800, 600]
        ]
    ) {
        $this->uploadDirectory = rtrim($uploadDirectory, '/');
        $this->publicPath = rtrim($publicPath, '/');
        $this->slugger = $slugger;
        $this->logger = $logger;
        $this->allowedMimeTypes = $allowedMimeTypes;
        $this->maxFileSize = $maxFileSize;
        $this->createThumbnails = $createThumbnails;
        $this->thumbnailSizes = $thumbnailSizes;
    }

    /**
     * Завантажує файл на сервер
     */
    public function upload(UploadedFile $file, string $subdirectory = ''): array
    {
        try {
            // Валідація файлу
            $this->validateFile($file);
            
            // Генеруємо унікальне ім'я
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $extension = $file->guessExtension() ?: $file->getClientOriginalExtension();
            $fileName = $safeFilename . '-' . uniqid() . '.' . $extension;
            
            // Формуємо повний шлях
            $fullUploadDir = $this->getFullUploadDirectory($subdirectory);
            $relativePath = $subdirectory ? $subdirectory . '/' . $fileName : $fileName;
            $fullPath = $fullUploadDir . '/' . $fileName;
            
            // Створюємо директорії
            $this->ensureDirectoryExists($fullUploadDir);
            
            // Переміщуємо файл
            $file->move($fullUploadDir, $fileName);
            
            $result = [
                'success' => true,
                'originalName' => $originalFilename,
                'fileName' => $fileName,
                'relativePath' => $relativePath,
                'fullPath' => $fullPath,
                'url' => $this->publicPath . '/' . $relativePath,
                'extension' => $extension,
                'mimeType' => $file->getMimeType(),
                'size' => $file->getSize(),
                'dimensions' => null,
                'thumbnails' => []
            ];
            
            // Якщо це зображення, отримуємо розміри та створюємо мініатюри
            if (str_starts_with($file->getMimeType(), 'image/')) {
                $dimensions = $this->getImageDimensions($fullPath);
                $result['dimensions'] = $dimensions;
                
                if ($this->createThumbnails) {
                    $result['thumbnails'] = $this->createImageThumbnails($fullPath, $fileName, $subdirectory);
                }
            }
            
            $this->logger->info('File uploaded successfully', [
                'file' => $fileName,
                'path' => $relativePath,
                'size' => $file->getSize()
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error('File upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'originalName' => $file->getClientOriginalName()
            ];
        }
    }
    
    /**
     * Завантажує кілька файлів
     */
    public function uploadMultiple(array $files, string $subdirectory = ''): array
    {
        $results = [];
        
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $results[] = $this->upload($file, $subdirectory);
            }
        }
        
        return $results;
    }
    
    /**
     * Видаляє файл
     */
    public function delete(string $relativePath): bool
    {
        try {
            $fullPath = $this->uploadDirectory . '/' . ltrim($relativePath, '/');
            
            if (!file_exists($fullPath)) {
                $this->logger->warning('File not found for deletion', ['path' => $fullPath]);
                return false;
            }
            
            // Видаляємо основний файл
            $deleted = unlink($fullPath);
            
            if ($deleted) {
                $this->logger->info('File deleted successfully', ['path' => $fullPath]);
                
                // Видаляємо мініатюри, якщо вони є
                $this->deleteThumbnails($relativePath);
            }
            
            return $deleted;
            
        } catch (\Exception $e) {
            $this->logger->error('File deletion failed', [
                'error' => $e->getMessage(),
                'path' => $relativePath
            ]);
            
            return false;
        }
    }
    
    /**
     * Отримує інформацію про файл
     */
    public function getFileInfo(string $relativePath): ?array
    {
        $fullPath = $this->uploadDirectory . '/' . ltrim($relativePath, '/');
        
        if (!file_exists($fullPath)) {
            return null;
        }
        
        $file = new File($fullPath);
        $extension = $file->guessExtension();
        $mimeType = $file->getMimeType();
        
        $info = [
            'name' => basename($relativePath),
            'path' => $relativePath,
            'fullPath' => $fullPath,
            'url' => $this->publicPath . '/' . $relativePath,
            'size' => $file->getSize(),
            'extension' => $extension,
            'mimeType' => $mimeType,
            'lastModified' => $file->getMTime(),
            'isImage' => str_starts_with($mimeType, 'image/')
        ];
        
        if ($info['isImage']) {
            $info['dimensions'] = $this->getImageDimensions($fullPath);
            $info['thumbnails'] = $this->getThumbnailPaths($relativePath);
        }
        
        return $info;
    }
    
    /**
     * Перевіряє, чи існує файл
     */
    public function fileExists(string $relativePath): bool
    {
        $fullPath = $this->uploadDirectory . '/' . ltrim($relativePath, '/');
        return file_exists($fullPath);
    }
    
    /**
     * Отримує список файлів у директорії
     */
    public function listFiles(string $subdirectory = '', array $extensions = []): array
    {
        $directory = $this->getFullUploadDirectory($subdirectory);
        
        if (!file_exists($directory)) {
            return [];
        }
        
        $files = [];
        $items = scandir($directory);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $fullPath = $directory . '/' . $item;
            $relativePath = $subdirectory ? $subdirectory . '/' . $item : $item;
            
            if (is_file($fullPath)) {
                if (empty($extensions) || in_array(pathinfo($item, PATHINFO_EXTENSION), $extensions)) {
                    $files[] = $this->getFileInfo($relativePath);
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Створює мініатюри для зображення
     */
    private function createImageThumbnails(string $sourcePath, string $fileName, string $subdirectory = ''): array
    {
        $thumbnails = [];
        
        foreach ($this->thumbnailSizes as $sizeName => $dimensions) {
            list($width, $height) = $dimensions;
            
            $thumbFileName = pathinfo($fileName, PATHINFO_FILENAME) . '_' . $sizeName . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
            $thumbRelativePath = $subdirectory ? $subdirectory . '/thumbs/' . $thumbFileName : 'thumbs/' . $thumbFileName;
            $thumbFullPath = $this->uploadDirectory . '/' . $thumbRelativePath;
            
            // Створюємо директорію для мініатюр
            $thumbDir = dirname($thumbFullPath);
            $this->ensureDirectoryExists($thumbDir);
            
            // Створюємо мініатюру
            if ($this->createThumbnail($sourcePath, $thumbFullPath, $width, $height)) {
                $thumbnails[$sizeName] = [
                    'path' => $thumbRelativePath,
                    'url' => $this->publicPath . '/' . $thumbRelativePath,
                    'width' => $width,
                    'height' => $height
                ];
            }
        }
        
        return $thumbnails;
    }
    
    /**
     * Створює одну мініатюру
     */
    private function createThumbnail(string $sourcePath, string $destinationPath, int $width, int $height): bool
    {
        try {
            $imageInfo = getimagesize($sourcePath);
            if (!$imageInfo) {
                return false;
            }
            
            list($origWidth, $origHeight, $type) = $imageInfo;
            
            // Визначаємо тип зображення
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $sourceImage = imagecreatefromjpeg($sourcePath);
                    break;
                case IMAGETYPE_PNG:
                    $sourceImage = imagecreatefrompng($sourcePath);
                    break;
                case IMAGETYPE_GIF:
                    $sourceImage = imagecreatefromgif($sourcePath);
                    break;
                case IMAGETYPE_WEBP:
                    $sourceImage = imagecreatefromwebp($sourcePath);
                    break;
                default:
                    return false;
            }
            
            if (!$sourceImage) {
                return false;
            }
            
            // Обчислюємо нові розміри зі збереженням пропорцій
            $ratio = min($width / $origWidth, $height / $origHeight);
            $newWidth = (int)($origWidth * $ratio);
            $newHeight = (int)($origHeight * $ratio);
            
            // Створюємо нове зображення
            $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
            
            // Для PNG та GIF зберігаємо прозорість
            if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
                imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
            }
            
            // Копіюємо та змінюємо розмір
            imagecopyresampled(
                $thumbnail, $sourceImage,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $origWidth, $origHeight
            );
            
            // Зберігаємо мініатюру
            switch ($type) {
                case IMAGETYPE_JPEG:
                    imagejpeg($thumbnail, $destinationPath, 85);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($thumbnail, $destinationPath, 8);
                    break;
                case IMAGETYPE_GIF:
                    imagegif($thumbnail, $destinationPath);
                    break;
                case IMAGETYPE_WEBP:
                    imagewebp($thumbnail, $destinationPath, 85);
                    break;
            }
            
            // Звільняємо пам'ять
            imagedestroy($sourceImage);
            imagedestroy($thumbnail);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Thumbnail creation failed', [
                'error' => $e->getMessage(),
                'source' => $sourcePath
            ]);
            
            return false;
        }
    }
    
    /**
     * Видаляє мініатюри файлу
     */
    private function deleteThumbnails(string $relativePath): void
    {
        $fileName = basename($relativePath);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $directory = dirname($relativePath);
        
        foreach ($this->thumbnailSizes as $sizeName => $dimensions) {
            $thumbFileName = $baseName . '_' . $sizeName . '.' . $extension;
            $thumbRelativePath = ($directory !== '.' ? $directory . '/' : '') . 'thumbs/' . $thumbFileName;
            $thumbFullPath = $this->uploadDirectory . '/' . $thumbRelativePath;
            
            if (file_exists($thumbFullPath)) {
                unlink($thumbFullPath);
            }
        }
    }
    
    /**
     * Отримує шляхи до мініатюр
     */
    private function getThumbnailPaths(string $relativePath): array
    {
        $thumbnails = [];
        $fileName = basename($relativePath);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $directory = dirname($relativePath);
        
        foreach ($this->thumbnailSizes as $sizeName => $dimensions) {
            $thumbFileName = $baseName . '_' . $sizeName . '.' . $extension;
            $thumbRelativePath = ($directory !== '.' ? $directory . '/' : '') . 'thumbs/' . $thumbFileName;
            $thumbFullPath = $this->uploadDirectory . '/' . $thumbRelativePath;
            
            if (file_exists($thumbFullPath)) {
                list($width, $height) = $dimensions;
                $thumbnails[$sizeName] = [
                    'path' => $thumbRelativePath,
                    'url' => $this->publicPath . '/' . $thumbRelativePath,
                    'width' => $width,
                    'height' => $height
                ];
            }
        }
        
        return $thumbnails;
    }
    
    /**
     * Валідує файл перед завантаженням
     */
    private function validateFile(UploadedFile $file): void
    {
        // Перевірка розміру
        if ($file->getSize() > $this->maxFileSize) {
            throw new \Exception(
                sprintf('Файл занадто великий. Максимальний розмір: %s', 
                $this->formatBytes($this->maxFileSize))
            );
        }
        
        // Перевірка MIME-типу
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            throw new \Exception(
                sprintf('Недопустимий тип файлу: %s. Дозволені типи: %s', 
                $mimeType, implode(', ', $this->allowedMimeTypes))
            );
        }
        
        // Додаткова перевірка для зображень
        if (str_starts_with($mimeType, 'image/')) {
            $imageInfo = @getimagesize($file->getPathname());
            if (!$imageInfo) {
                throw new \Exception('Файл не є валідним зображенням');
            }
        }
    }
    
    /**
     * Отримує розміри зображення
     */
    private function getImageDimensions(string $path): ?array
    {
        $imageInfo = @getimagesize($path);
        
        if ($imageInfo) {
            return [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1]
            ];
        }
        
        return null;
    }
    
    /**
     * Формує повний шлях до директорії завантаження
     */
    private function getFullUploadDirectory(string $subdirectory = ''): string
    {
        $directory = $this->uploadDirectory;
        
        if ($subdirectory) {
            $directory .= '/' . trim($subdirectory, '/');
        }
        
        return $directory;
    }
    
    /**
     * Створює директорію, якщо вона не існує
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (!file_exists($directory)) {
            if (!mkdir($directory, 0777, true)) {
                throw new \Exception(sprintf('Не вдалося створити директорію: %s', $directory));
            }
        }
    }
    
    /**
     * Форматує байти в читабельний формат
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    // Гетери
    public function getUploadDirectory(): string
    {
        return $this->uploadDirectory;
    }
    
    public function getPublicPath(): string
    {
        return $this->publicPath;
    }
    
    public function getAllowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }
    
    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }
}