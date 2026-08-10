<?php

namespace App\Repository;

use App\Entity\Admin\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * Знайти популярні теги (за загальною кількістю використань)
     */
    public function findPopularTags(int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.totalUsageCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Знайти популярні теги для статей
     */
    public function findPopularForArticles(int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.articleUsageCount', 'DESC')
            ->where('t.articleUsageCount > 0')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Знайти популярні теги для блогів
     */
    public function findPopularForBlogs(int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.blogUsageCount', 'DESC')
            ->where('t.blogUsageCount > 0')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Знайти активні теги
     */
    public function findActiveTags(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.isActive = true')
            ->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.totalUsageCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Знайти теги за типом контенту
     */
    public function findByContentType(string $type): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.isActive = true');

        if ($type === 'article') {
            $qb->andWhere('t.articleUsageCount > 0')
               ->orderBy('t.articleUsageCount', 'DESC');
        } elseif ($type === 'blog') {
            $qb->andWhere('t.blogUsageCount > 0')
               ->orderBy('t.blogUsageCount', 'DESC');
        } else {
            $qb->orderBy('t.totalUsageCount', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Знайти теги за пошуковим запитом
     */
    public function search(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.name LIKE :query')
            ->orWhere('t.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->andWhere('t.isActive = true')
            ->orderBy('t.totalUsageCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Отримати статистику тегів
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select([
                'COUNT(t.id) as total_tags',
                'SUM(t.totalUsageCount) as total_usage',
                'SUM(t.articleUsageCount) as article_usage',
                'SUM(t.blogUsageCount) as blog_usage',
                'AVG(t.totalUsageCount) as avg_usage',
            ]);

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Знайти невикористані теги
     */
    public function findUnusedTags(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.totalUsageCount = 0')
            ->andWhere('t.isActive = true')
            ->getQuery()
            ->getResult();
    }

    /**
     * Знайти теги з подібними назвами
     */
    public function findSimilarTags(Tag $tag, int $limit = 5): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.id != :id')
            ->andWhere('t.isActive = true')
            ->setParameter('id', $tag->getId())
            ->orderBy('t.totalUsageCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Оновити лічильники використання тегів
     */
    public function updateUsageCounters(): void
    {
        $tags = $this->findAll();
        
        foreach ($tags as $tag) {
            $articleCount = count($tag->getArticles());
            $blogCount = count($tag->getBlogPosts());
            $totalCount = $articleCount + $blogCount;
            
            $tag->setArticleUsageCount($articleCount);
            $tag->setBlogUsageCount($blogCount);
            $tag->setTotalUsageCount($totalCount);
        }
        
        $this->getEntityManager()->flush();
    }
}