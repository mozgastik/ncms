<?php

namespace App\Repository;

use App\Entity\Article\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    public function findPendingForModeration(int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', Article::STATUS_PENDING)
            ->orderBy('a.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByStatus(string $status, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', $status)
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $query = $qb->getQuery();
        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
            'currentPage' => $page,
            'totalPages' => ceil(count($paginator) / $limit)
        ];
    }

    public function findByAuthor($user, int $page = 1, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.author = :author')
            ->setParameter('author', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $query = $qb->getQuery();
        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
            'currentPage' => $page,
            'totalPages' => ceil(count($paginator) / $limit)
        ];
    }

    /**
     * Отримує статистику статей по автору
     */
    public function getStatsByAuthor($user): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id) as total')
            ->addSelect('SUM(CASE WHEN a.status = :draft THEN 1 ELSE 0 END) as draft')
            ->addSelect('SUM(CASE WHEN a.status = :pending THEN 1 ELSE 0 END) as pending')
            ->addSelect('SUM(CASE WHEN a.status = :approved THEN 1 ELSE 0 END) as approved')
            ->addSelect('SUM(CASE WHEN a.status = :published THEN 1 ELSE 0 END) as published')
            ->addSelect('SUM(CASE WHEN a.status = :rejected THEN 1 ELSE 0 END) as rejected')
            ->addSelect('SUM(CASE WHEN a.status = :archived THEN 1 ELSE 0 END) as archived')
            ->setParameter('draft', Article::STATUS_DRAFT)
            ->setParameter('pending', Article::STATUS_PENDING)
            ->setParameter('approved', Article::STATUS_APPROVED)
            ->setParameter('published', Article::STATUS_PUBLISHED)
            ->setParameter('rejected', Article::STATUS_REJECTED)
            ->setParameter('archived', Article::STATUS_ARCHIVED)
            ->where('a.author = :author')
            ->setParameter('author', $user);

        $result = $qb->getQuery()->getSingleResult();
        
        // Переконуємось, що всі значення є числами
        return [
            'total' => (int) $result['total'],
            'draft' => (int) $result['draft'],
            'pending' => (int) $result['pending'],
            'approved' => (int) $result['approved'],
            'published' => (int) $result['published'],
            'rejected' => (int) $result['rejected'],
            'archived' => (int) $result['archived'],
        ];
    }

    public function findPublished(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.status = :status')
            ->andWhere('a.publishedAt <= :now')
            ->setParameter('status', Article::STATUS_PUBLISHED)
            ->setParameter('now', new \DateTime())
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function search(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.title LIKE :query')
            ->orWhere('a.content LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


/**
 * Підраховує кількість статей, опублікованих сьогодні
 */
public function countTodayArticles(): int
{
    $today = new \DateTime();
    $today->setTime(0, 0, 0);
    $tomorrow = clone $today;
    $tomorrow->modify('+1 day');
    
    return $this->createQueryBuilder('a')
        ->select('COUNT(a.id)')
        ->where('a.publishedAt >= :today')
        ->andWhere('a.publishedAt < :tomorrow')
        ->andWhere('a.status = :status')
        ->setParameter('today', $today)
        ->setParameter('tomorrow', $tomorrow)
        ->setParameter('status', Article::STATUS_PUBLISHED)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Підраховує кількість статей за статусом
 */
public function countByStatus(string $status): int
{
    return $this->createQueryBuilder('a')
        ->select('COUNT(a.id)')
        ->where('a.status = :status')
        ->setParameter('status', $status)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Підраховує кількість статей за категорією
 */
public function countByCategory(int $categoryId): int
{
    return $this->createQueryBuilder('a')
        ->select('COUNT(a.id)')
        ->where('a.category = :category')
        ->setParameter('category', $categoryId)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Підраховує кількість статей за автором
 */
public function countByAuthor(int $authorId): int
{
    return $this->createQueryBuilder('a')
        ->select('COUNT(a.id)')
        ->where('a.author = :author')
        ->setParameter('author', $authorId)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Знаходить схожі статті на основі категорії та тегів
 */
public function findRelatedArticles(Article $article, int $limit = 5): array
{
    $qb = $this->createQueryBuilder('a')
        ->where('a.id != :currentId')
        ->andWhere('a.status = :status')
        ->setParameter('currentId', $article->getId())
        ->setParameter('status', Article::STATUS_PUBLISHED)
        ->setMaxResults($limit);
    
    // Пріоритет статтям з тією ж категорією
    if ($article->getCategory()) {
        $qb->addOrderBy('CASE WHEN a.category = :category THEN 1 ELSE 0 END', 'DESC')
           ->setParameter('category', $article->getCategory());
    }
    
    // Додаємо сортування за датою та популярністю
    $qb->addOrderBy('a.publishedAt', 'DESC')
       ->addOrderBy('a.views', 'DESC');
    
    return $qb->getQuery()->getResult();
}

/**
 * Знаходить популярні статті (за кількістю переглядів)
 */
public function findPopularArticles(int $limit = 5): array
{
    return $this->createQueryBuilder('a')
        ->where('a.status = :status')
        ->setParameter('status', Article::STATUS_PUBLISHED)
        ->orderBy('a.views', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}

/**
 * Знаходить рекомендовані статті
 */
public function findRecommendedArticles(int $limit = 3): array
{
    return $this->createQueryBuilder('a')
        ->where('a.status = :status')
        ->andWhere('a.isFeatured = :featured')
        ->setParameter('status', Article::STATUS_PUBLISHED)
        ->setParameter('featured', true)
        ->orderBy('a.publishedAt', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}

/**
 * Знаходить останні статті
 */
public function findLatestArticles(int $limit = 10): array
{
    return $this->createQueryBuilder('a')
        ->where('a.status = :status')
        ->setParameter('status', Article::STATUS_PUBLISHED)
        ->orderBy('a.publishedAt', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}

/**
 * Знаходить статті за категорією
 */
public function findArticlesByCategory(int $categoryId, int $limit = 10): array
{
    return $this->createQueryBuilder('a')
        ->where('a.status = :status')
        ->andWhere('a.category = :category')
        ->setParameter('status', Article::STATUS_PUBLISHED)
        ->setParameter('category', $categoryId)
        ->orderBy('a.publishedAt', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}

/**
 * Знаходить статті за тегом
 */
public function findArticlesByTag(int $tagId, int $limit = 10): array
{
    return $this->createQueryBuilder('a')
        ->innerJoin('a.tags', 't')
        ->where('a.status = :status')
        ->andWhere('t.id = :tag')
        ->setParameter('status', Article::STATUS_PUBLISHED)
        ->setParameter('tag', $tagId)
        ->orderBy('a.publishedAt', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}

/**
 * Отримує всі статті з пагінацією
 */
public function findAllWithPagination(int $page = 1, int $limit = 20): array
{
    $qb = $this->createQueryBuilder('a')
        ->orderBy('a.createdAt', 'DESC')
        ->setFirstResult(($page - 1) * $limit)
        ->setMaxResults($limit);

    $query = $qb->getQuery();
    $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);

    return [
        'items' => iterator_to_array($paginator),
        'total' => count($paginator),
        'currentPage' => $page,
        'totalPages' => ceil(count($paginator) / $limit)
    ];
}
}