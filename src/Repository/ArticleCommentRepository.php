<?php

namespace App\Repository;

use App\Entity\Article\ArticleComment;
use App\Entity\Article\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ArticleCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleComment::class);
    }

    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.article', 'a')
            ->addSelect('a')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPending(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isApproved = :approved')
            ->setParameter('approved', false)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByArticleId(int $articleId): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.article = :articleId')
            ->andWhere('c.isApproved = :approved')
            ->setParameter('articleId', $articleId)
            ->setParameter('approved', true)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

   public function findApprovedCommentsByArticle(Article $article): array
{
    return $this->createQueryBuilder('c')
        ->leftJoin('c.likes', 'l')
        ->addSelect('COUNT(l.id) as HIDDEN likeCount')
        ->andWhere('c.article = :article')
        ->andWhere('c.isApproved = :approved')
        ->andWhere('c.isSpam = :spam')
        ->andWhere('c.isDeleted = false')
        ->andWhere('l.isLike = true OR l.id IS NULL') // Тільки лайки або відсутність
        ->setParameter('article', $article)
        ->setParameter('approved', true)
        ->setParameter('spam', false)
        ->groupBy('c.id')
        ->orderBy('likeCount', 'DESC')
        ->addOrderBy('c.createdAt', 'DESC')
        ->getQuery()
        ->getResult();
}

 public function findAllCommentsByArticle(Article $article): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.article = :article')
            ->setParameter('article', $article)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

}