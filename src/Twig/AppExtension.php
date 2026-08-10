<?php

namespace App\Twig;

use App\Entity\Article\Category;
use App\Entity\Article\Article;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('categories', [$this, 'getCategories']),
            new TwigFunction('recent_articles', [$this, 'getRecentArticles']),
            new TwigFunction('popular_articles', [$this, 'getPopularArticles']),
            new TwigFunction('get_status_color_class', [$this, 'getStatusColorClass']),
            new TwigFunction('clean_excerpt', [$this, 'cleanExcerpt']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('ago', [$this, 'timeAgo']),
            new TwigFilter('min', [$this, 'min']),
            new TwigFilter('max', [$this, 'max']),
            new TwigFilter('html_entity_decode', [$this, 'decodeEntities']),
            new TwigFilter('decode_entities', [$this, 'decodeEntities']),
        ];
    }

    public function getCategories(): array
    {
        return $this->entityManager
            ->getRepository(Category::class)
            ->findBy(['isActive' => true], ['sortOrder' => 'ASC', 'name' => 'ASC']);
    }

    public function getRecentArticles(int $limit = 5): array
    {
        return $this->entityManager
            ->getRepository(Article::class)
            ->findBy(
                ['isPublished' => true],
                ['publishedAt' => 'DESC'],
                $limit
            );
    }

    public function getPopularArticles(int $limit = 5): array
    {
        return $this->entityManager
            ->getRepository(Article::class)
            ->createQueryBuilder('a')
            ->where('a.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('a.views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getStatusColorClass(string $status): string
    {
        return match($status) {
            Article::STATUS_DRAFT => 'bg-yellow-100 text-yellow-800 border-yellow-200',
            Article::STATUS_PENDING => 'bg-blue-100 text-blue-800 border-blue-200',
            Article::STATUS_PUBLISHED => 'bg-green-100 text-green-800 border-green-200',
            Article::STATUS_ARCHIVED => 'bg-gray-100 text-gray-800 border-gray-200',
            default => 'bg-gray-100 text-gray-800 border-gray-200',
        };
    }

    public function timeAgo(\DateTimeInterface $date): string
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($date);
        
        // Якщо дата в майбутньому
        if ($date > $now) {
            return 'у майбутньому';
        }
        
        if ($diff->y > 0) {
            return $diff->y . ' ' . $this->pluralize($diff->y, 'рік', 'роки', 'років') . ' тому';
        }
        if ($diff->m > 0) {
            return $diff->m . ' ' . $this->pluralize($diff->m, 'місяць', 'місяці', 'місяців') . ' тому';
        }
        if ($diff->d > 0) {
            if ($diff->d == 1) {
                return 'вчора';
            }
            if ($diff->d == 2) {
                return 'позавчора';
            }
            return $diff->d . ' ' . $this->pluralize($diff->d, 'день', 'дні', 'днів') . ' тому';
        }
        if ($diff->h > 0) {
            return $diff->h . ' ' . $this->pluralize($diff->h, 'година', 'години', 'годин') . ' тому';
        }
        if ($diff->i > 0) {
            return $diff->i . ' ' . $this->pluralize($diff->i, 'хвилина', 'хвилини', 'хвилин') . ' тому';
        }
        
        return 'щойно';
    }

    /**
     * Допоміжний метод для правильного відмінювання слів
     */
    private function pluralize(int $number, string $one, string $few, string $many): string
    {
        $number = abs($number) % 100;
        $lastDigit = $number % 10;
        
        if ($number > 10 && $number < 20) {
            return $many;
        }
        
        if ($lastDigit > 1 && $lastDigit < 5) {
            return $few;
        }
        
        if ($lastDigit == 1) {
            return $one;
        }
        
        return $many;
    }
    
    public function min($value, $min): int
    {
        return min($value, $min);
    }

    public function max($value, $max): int
    {
        return max($value, $max);
    }
    
   public function decodeEntities(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    public function cleanExcerpt(Article $article, int $length = 130): string
{
    $excerpt = $article->getExcerpt() 
        ?: strip_tags($article->getContent());
    
    if (mb_strlen($excerpt) > $length) {
        $excerpt = mb_substr($excerpt, 0, $length) . '...';
    }
    
    return html_entity_decode($excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
}