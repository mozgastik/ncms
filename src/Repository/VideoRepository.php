<?php
// src/Repository/VideoRepository.php

namespace App\Repository;

use App\Entity\System\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<Video>
 */
class VideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Video::class);
    }

    /**
     * Отримати опубліковані відео з пагінацією
     */
    public function findPublished(int $page = 1, int $limit = 12): array
    {
        $query = $this->createQueryBuilder('v')
            ->where('v.isPublished = :published')
            ->andWhere('v.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('now', new \DateTime())
            ->orderBy('v.publishedAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $paginator = new Paginator($query);

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
            'pages' => ceil(count($paginator) / $limit),
            'current' => $page,
        ];
    }

    /**
     * Отримати рекомендовані відео
     */
    public function findRecommended(Video $video, int $limit = 6): array
    {
        $qb = $this->createQueryBuilder('v')
            ->where('v.isPublished = :published')
            ->andWhere('v.publishedAt <= :now')
            ->andWhere('v.id != :current')
            ->setParameter('published', true)
            ->setParameter('now', new \DateTime())
            ->setParameter('current', $video->getId())
            ->orderBy('v.views', 'DESC')
            ->setMaxResults($limit);

        // Якщо є категорія, додаємо її як пріоритет
        if ($video->getCategory()) {
            $qb->andWhere('v.category = :category')
               ->setParameter('category', $video->getCategory());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Отримати популярні відео
     */
    public function findPopular(int $limit = 6): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.isPublished = :published')
            ->andWhere('v.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('now', new \DateTime())
            ->orderBy('v.views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Отримати останні відео
     */
    public function findLatest(int $limit = 6): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.isPublished = :published')
            ->andWhere('v.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('now', new \DateTime())
            ->orderBy('v.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Отримати відео за категорією
     */
    public function findByCategory(int $categoryId, int $limit = 12): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.isPublished = :published')
            ->andWhere('v.publishedAt <= :now')
            ->andWhere('v.category = :category')
            ->setParameter('published', true)
            ->setParameter('now', new \DateTime())
            ->setParameter('category', $categoryId)
            ->orderBy('v.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Пошук відео
     */
    public function search(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.isPublished = :published')
            ->andWhere('v.publishedAt <= :now')
            ->andWhere('v.title LIKE :query OR v.description LIKE :query OR v.tags LIKE :query')
            ->setParameter('published', true)
            ->setParameter('now', new \DateTime())
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('v.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}