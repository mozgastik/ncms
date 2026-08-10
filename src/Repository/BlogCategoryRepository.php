<?php

namespace App\Repository;

use App\Entity\Article\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlogCategory>
 */
class BlogCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Знайти всі категорії з кількістю блогів
     */
    public function findAllWithCount(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'COUNT(bp.id) as postCount')
            ->leftJoin('c.blogPosts', 'bp')
            ->where('bp.status = :status OR bp.id IS NULL')
            ->setParameter('status', BlogPost::STATUS_PUBLISHED)
            ->groupBy('c.id')
            ->orderBy('c.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}