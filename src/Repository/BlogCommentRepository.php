<?php
// src/Repository/BlogCommentRepository.php

namespace App\Repository;

use App\Entity\Blog\BlogComment;
use App\Entity\Blog\BlogPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlogComment>
 */
class BlogCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogComment::class);
    }

    /**
     * Знайти схвалені коментарі для статті
     */
    public function findApprovedCommentsByBlogPost(BlogPost $blogPost, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.blogPost = :blogPost')
            ->andWhere('c.isApproved = :approved')
            ->andWhere('c.isSpam = :notSpam')
            ->andWhere('c.parent IS NULL')
            ->setParameter('blogPost', $blogPost)
            ->setParameter('approved', true)
            ->setParameter('notSpam', false)
            ->orderBy('c.createdAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Знайти коментарі на модерації
     */
    public function findPendingComments(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isApproved = :approved')
            ->andWhere('c.isSpam = :notSpam')
            ->setParameter('approved', false)
            ->setParameter('notSpam', false)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Знайти спам коментарі
     */
    public function findSpamComments(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isSpam = :spam')
            ->setParameter('spam', true)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Знайти останні коментарі
     */
    public function findLatestComments(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isApproved = :approved')
            ->andWhere('c.isSpam = :notSpam')
            ->setParameter('approved', true)
            ->setParameter('notSpam', false)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Підрахунок коментарів користувача
     */
    public function countUserComments(int $userId): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Знайти коментарі користувача
     */
    public function findUserComments(int $userId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Підрахунок схвалених коментарів для статті
     */
    public function countApprovedCommentsByBlogPost(BlogPost $blogPost): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.blogPost = :blogPost')
            ->andWhere('c.isApproved = :approved')
            ->andWhere('c.isSpam = :notSpam')
            ->setParameter('blogPost', $blogPost)
            ->setParameter('approved', true)
            ->setParameter('notSpam', false)
            ->getQuery()
            ->getSingleScalarResult();
    }
}