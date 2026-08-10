<?php

namespace App\Repository;

use App\Entity\Blog\BlogPost;
use App\Entity\Article\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlogPost>
 */
class BlogPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogPost::class);
    }

    /**
     * Знайти популярні блоги
     */
    public function findPopular(int $limit = 10): array
    {
        return $this->createQueryBuilder('bp')
            ->where('bp.status = :status')
            ->andWhere('bp.publishedAt <= :now OR bp.publishedAt IS NULL')
            ->setParameter('status', BlogPost::STATUS_PUBLISHED)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('bp.viewCount', 'DESC')
            ->addOrderBy('bp.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Знайти схожі блоги
     */
    public function findRelatedPosts(BlogPost $post, int $limit = 3): array
    {
        $qb = $this->createQueryBuilder('bp')
            ->where('bp.status = :status')
            ->andWhere('bp.id != :id')
            ->andWhere('bp.publishedAt <= :now OR bp.publishedAt IS NULL')
            ->setParameter('status', BlogPost::STATUS_PUBLISHED)
            ->setParameter('id', $post->getId())
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('bp.publishedAt', 'DESC')
            ->setMaxResults($limit);

        // Спробуємо знайти за категорією
        if ($post->getCategory()) {
            $qb->andWhere('bp.category = :category')
                ->setParameter('category', $post->getCategory());
        }

        $result = $qb->getQuery()->getResult();

        // Якщо не знайшли достатньо за категорією, додамо за тегами
        if (count($result) < $limit && $post->getTags()->count() > 0) {
            $tagIds = [];
            foreach ($post->getTags() as $tag) {
                $tagIds[] = $tag->getId();
            }

            $additionalQb = $this->createQueryBuilder('bp')
                ->innerJoin('bp.tags', 't')
                ->where('bp.status = :status')
                ->andWhere('bp.id != :id')
                ->andWhere('bp.id NOT IN (:excludeIds)')
                ->andWhere('t.id IN (:tagIds)')
                ->andWhere('bp.publishedAt <= :now OR bp.publishedAt IS NULL')
                ->setParameter('status', BlogPost::STATUS_PUBLISHED)
                ->setParameter('id', $post->getId())
                ->setParameter('excludeIds', array_map(fn($p) => $p->getId(), $result))
                ->setParameter('tagIds', $tagIds)
                ->setParameter('now', new \DateTimeImmutable())
                ->orderBy('bp.publishedAt', 'DESC')
                ->setMaxResults($limit - count($result));

            $additionalResults = $additionalQb->getQuery()->getResult();
            $result = array_merge($result, $additionalResults);
        }

        return $result;
    }

    /**
     * Пошуковий запит
     */
    public function createSearchQueryBuilder(string $query)
    {
        return $this->createQueryBuilder('bp')
            ->where('bp.status = :status')
            ->andWhere('bp.publishedAt <= :now OR bp.publishedAt IS NULL')
            ->andWhere('bp.title LIKE :query OR bp.content LIKE :query OR bp.excerpt LIKE :query')
            ->setParameter('status', BlogPost::STATUS_PUBLISHED)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('bp.publishedAt', 'DESC');
    }

    /**
     * Отримати блоги для головної сторінки
     */
    public function findForHomepage(): array
    {
        return [
            'featured' => $this->findFeatured(),
            'latest' => $this->findLatest(6),
            'breaking' => $this->findBreaking(3),
        ];
    }

    /**
     * Рекомендовані блоги
     */
    public function findFeatured(int $limit = 3): array
    {
        return $this->createQueryBuilder('bp')
            ->where('bp.status = :status')
            ->andWhere('bp.isFeatured = :featured')
            ->andWhere('bp.publishedAt <= :now OR bp.publishedAt IS NULL')
            ->setParameter('status', BlogPost::STATUS_PUBLISHED)
            ->setParameter('featured', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('bp.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Останні блоги
     */
    public function findLatest(int $limit = 6): array
    {
        return $this->createQueryBuilder('bp')
            ->where('bp.status = :status')
            ->andWhere('bp.publishedAt <= :now OR bp.publishedAt IS NULL')
            ->setParameter('status', BlogPost::STATUS_PUBLISHED)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('bp.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Стрічка новин
     */
    public function findBreaking(int $limit = 3): array
    {
        return $this->createQueryBuilder('bp')
            ->where('bp.status = :status')
            ->andWhere('bp.isBreaking = :breaking')
            ->andWhere('bp.publishedAt <= :now OR bp.publishedAt IS NULL')
            ->setParameter('status', BlogPost::STATUS_PUBLISHED)
            ->setParameter('breaking', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('bp.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Блоги користувача
     */
    public function findByUser($user, array $statuses = null): array
    {
        $qb = $this->createQueryBuilder('bp')
            ->where('bp.author = :author')
            ->setParameter('author', $user)
            ->orderBy('bp.createdAt', 'DESC');

        if ($statuses) {
            $qb->andWhere('bp.status IN (:statuses)')
                ->setParameter('statuses', $statuses);
        }

        return $qb->getQuery()->getResult();
    }

    public function findWithCategoryAndAuthor(array $criteria = [], array $orderBy = null, $limit = null, $offset = null)
    {
        $qb = $this->createQueryBuilder('bp')
            ->leftJoin('bp.category', 'c')
            ->leftJoin('bp.author', 'u')
            ->addSelect('c', 'u');

        foreach ($criteria as $field => $value) {
            $qb->andWhere("bp.{$field} = :{$field}")
                ->setParameter($field, $value);
        }

        if ($orderBy) {
            foreach ($orderBy as $field => $direction) {
                $qb->addOrderBy("bp.{$field}", $direction);
            }
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        if ($offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }
}