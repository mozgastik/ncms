<?php
// src/Twig/VideoExtension.php

namespace App\Twig;

use App\Repository\VideoRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class VideoExtension extends AbstractExtension
{
    public function __construct(
        private readonly VideoRepository $videoRepository
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('latest_videos', $this->getLatestVideos(...)),
            new TwigFunction('popular_videos', $this->getPopularVideos(...)),
            new TwigFunction('featured_videos', $this->getFeaturedVideos(...)),
        ];
    }

    public function getLatestVideos(int $limit = 6): array
    {
        return $this->videoRepository->findLatest($limit);
    }

    public function getPopularVideos(int $limit = 6): array
    {
        return $this->videoRepository->findPopular($limit);
    }

    public function getFeaturedVideos(int $limit = 6): array
    {
        return $this->videoRepository->findBy(
            ['isPublished' => true, 'isFeatured' => true],
            ['publishedAt' => 'DESC'],
            $limit
        );
    }
}