<?php
// src/Service/VideoService.php

namespace App\Service;

use App\Entity\System\Video;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class VideoService
{
    public function __construct(
        private readonly VideoRepository $videoRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheItemPoolInterface $cache,
        private readonly HttpClientInterface $httpClient
    ) {}

    /**
     * Отримати відео з кешем
     */
    public function getVideo(int $id): ?Video
    {
        $cacheItem = $this->cache->getItem('video_' . $id);
        
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $video = $this->videoRepository->find($id);
        
        if ($video) {
            $cacheItem->set($video);
            $cacheItem->expiresAfter(3600); // 1 година
            $this->cache->save($cacheItem);
        }

        return $video;
    }

    /**
     * Отримати метадані з відеохостингу
     */
    public function fetchVideoMetadata(Video $video): array
    {
        $metadata = [];

        switch ($video->getSource()) {
            case Video::SOURCE_YOUTUBE:
                $metadata = $this->fetchYouTubeMetadata($video->getVideoId());
                break;
            case Video::SOURCE_VIMEO:
                $metadata = $this->fetchVimeoMetadata($video->getVideoId());
                break;
            case Video::SOURCE_RUTUBE:
                $metadata = $this->fetchRutubeMetadata($video->getVideoId());
                break;
        }

        return $metadata;
    }

    private function fetchYouTubeMetadata(string $videoId): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://www.googleapis.com/youtube/v3/videos', [
                'query' => [
                    'part' => 'snippet,contentDetails',
                    'id' => $videoId,
                    'key' => $_ENV['YOUTUBE_API_KEY'] ?? '',
                ],
            ]);

            $data = $response->toArray();
            
            if (empty($data['items'])) {
                return [];
            }

            $item = $data['items'][0];
            
            return [
                'title' => $item['snippet']['title'] ?? null,
                'description' => $item['snippet']['description'] ?? null,
                'thumbnail' => $item['snippet']['thumbnails']['maxres']['url'] ?? $item['snippet']['thumbnails']['high']['url'] ?? null,
                'duration' => $this->convertISO8601ToSeconds($item['contentDetails']['duration']),
                'tags' => implode(',', $item['snippet']['tags'] ?? []),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }  


    private function fetchVimeoMetadata(string $videoId): array
{
    try {
        // Vimeo API requires an access token
        $accessToken = $_ENV['VIMEO_ACCESS_TOKEN'] ?? '';
        
        if (!$accessToken) {
            return [];
        }

        $response = $this->httpClient->request('GET', 'https://api.vimeo.com/videos/' . $videoId, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/vnd.vimeo.*+json;version=3.4',
            ],
        ]);

        $data = $response->toArray();
        
        return [
            'title' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'thumbnail' => $data['pictures']['sizes'][count($data['pictures']['sizes']) - 1]['link'] ?? null,
            'duration' => $data['duration'] ?? null,
            'tags' => implode(',', array_column($data['tags'] ?? [], 'name')),
        ];
    } catch (\Exception $e) {
        // Логування помилки
        error_log('Vimeo API error: ' . $e->getMessage());
        return [];
    }
} 

/**
 * Отримати метадані з Telegram відео
 */
private function fetchTelegramMetadata(string $url): array
{
    try {
        // Telegram video oembed endpoint
        $response = $this->httpClient->request('GET', 'https://t.me/oembed', [
            'query' => [
                'url' => $url,
                'format' => 'json',
            ],
        ]);

        $data = $response->toArray();
        
        // Для Telegram потрібно парсити HTML для отримання деталей
        $html = $this->httpClient->request('GET', $url)->getContent();
        
        // Парсинг тривалості з HTML
        $duration = null;
        if (preg_match('/<time datetime="PT(\d+)M(\d+)S"/', $html, $matches)) {
            $duration = ($matches[1] * 60) + $matches[2];
        }
        
        return [
            'title' => $data['title'] ?? 'Telegram Video',
            'description' => $data['description'] ?? '',
            'thumbnail' => $data['thumbnail_url'] ?? null,
            'duration' => $duration,
            'tags' => 'telegram, video',
        ];
    } catch (\Exception $e) {
        error_log('Telegram API error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Отримати метадані з Facebook відео
 */
private function fetchFacebookMetadata(string $videoId): array
{
    try {
        $accessToken = $_ENV['FACEBOOK_ACCESS_TOKEN'] ?? '';
        
        if (!$accessToken) {
            return [];
        }

        // Facebook Graph API
        $response = $this->httpClient->request('GET', 'https://graph.facebook.com/v18.0/' . $videoId, [
            'query' => [
                'fields' => 'title,description,thumbnails,length,created_time,permalink_url',
                'access_token' => $accessToken,
            ],
        ]);

        $data = $response->toArray();
        
        return [
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'thumbnail' => $data['thumbnails']['data'][0]['uri'] ?? null,
            'duration' => $data['length'] ?? null,
            'tags' => '',
            'url' => $data['permalink_url'] ?? null,
        ];
    } catch (\Exception $e) {
        error_log('Facebook API error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Парсинг Facebook URL для отримання ID
 */
private function extractFacebookVideoId(string $url): ?string
{
    // https://www.facebook.com/watch/?v=123456789
    if (preg_match('/facebook\.com\/watch\/?\?v=(\d+)/', $url, $matches)) {
        return $matches[1];
    }
    
    // https://www.facebook.com/username/videos/123456789
    if (preg_match('/facebook\.com\/[^\/]+\/videos\/(\d+)/', $url, $matches)) {
        return $matches[1];
    }
    
    return null;
}

/**
 * Отримати метадані з Rutube відео (оновлений)
 */
private function fetchRutubeMetadata(string $videoId): array
{
    try {
        // Rutube API
        $response = $this->httpClient->request('GET', 'https://rutube.ru/api/video/' . $videoId . '/', [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; Symfony)',
                'Accept' => 'application/json',
            ],
        ]);

        $data = $response->toArray();
        
        return [
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'thumbnail' => $data['thumbnail_url'] ?? $data['picture_url'] ?? null,
            'duration' => $data['duration'] ?? null,
            'tags' => implode(',', $data['tags'] ?? []),
        ];
    } catch (\Exception $e) {
        error_log('Rutube API error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Універсальний метод для визначення джерела за URL
 */
public function detectSourceFromUrl(string $url): ?string
{
    $patterns = [
        Video::SOURCE_YOUTUBE => [
            'youtube\.com',
            'youtu\.be',
        ],
        Video::SOURCE_VIMEO => [
            'vimeo\.com',
        ],
        Video::SOURCE_RUTUBE => [
            'rutube\.ru',
        ],
        'telegram' => [
            't\.me',
            'telegram\.me',
        ],
        'facebook' => [
            'facebook\.com',
            'fb\.watch',
        ],
    ];

    foreach ($patterns as $source => $sourcePatterns) {
        foreach ($sourcePatterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $url)) {
                return $source;
            }
        }
    }

    return null;
}


    private function convertISO8601ToSeconds(string $iso): int
    {
        $interval = new \DateInterval($iso);
        return ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    }

    /**
     * Оновити статистику переглядів
     */
    public function trackView(Video $video): void
    {
        $video->incrementViews();
        $this->entityManager->flush();
        
        // Очистити кеш
        $this->cache->deleteItem('video_' . $video->getId());
    }

    /**
     * Отримати схожі відео
     */
    public function getRelatedVideos(Video $video, int $limit = 6): array
    {
        $cacheKey = 'related_videos_' . $video->getId();
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $related = $this->videoRepository->findRecommended($video, $limit);
        
        $cacheItem->set($related);
        $cacheItem->expiresAfter(3600);
        $this->cache->save($cacheItem);

        return $related;
    }
}