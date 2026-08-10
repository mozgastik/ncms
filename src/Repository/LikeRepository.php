<?php
// src/Repository/LikeRepository.php

namespace App\Repository;

use App\Entity\Article\Like;
use App\Entity\User\User;
use App\Entity\Article\Article;
use App\Entity\Article\ArticleComment; // ← Додайте цей імпорт
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Like>
 */
class LikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Like::class);
    }

    // ========== МЕТОДИ ДЛЯ СТАТТЕЙ ==========

    /**
     * Підрахунок лайків для статті
     */
    public function countLikesForArticle(int $articleId): int
    {
        return $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.article = :articleId')
            ->andWhere('l.isLike = true')
            ->setParameter('articleId', $articleId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Підрахунок дизлайків для статті
     */
    public function countDislikesForArticle(int $articleId): int
    {
        return $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.article = :articleId')
            ->andWhere('l.isLike = false')  // ← ВИПРАВЛЕНО (false для дизлайків)
            ->setParameter('articleId', $articleId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Знайти голос користувача для статті
     */
    public function findUserVoteForArticle(User $user, Article $article): ?Like
    {
        return $this->findOneBy([
            'user' => $user,
            'article' => $article
        ]);
    }

    // ========== МЕТОДИ ДЛЯ КОМЕНТАРІВ СТАТТЕЙ ==========

    /**
     * Підрахунок лайків для коментаря статті
     */
    public function countLikesForArticleComment(int $commentId): int
    {
        return $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.articleComment = :commentId')
            ->andWhere('l.isLike = true')
            ->setParameter('commentId', $commentId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Підрахунок дизлайків для коментаря статті
     */
    public function countDislikesForArticleComment(int $commentId): int
    {
        return $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.articleComment = :commentId')
            ->andWhere('l.isLike = false')  // ← ВИПРАВЛЕНО (false для дизлайків)
            ->setParameter('commentId', $commentId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Знайти голос користувача для коментаря статті
     */
    public function findUserVoteForArticleComment(User $user, ArticleComment $comment): ?Like  // ← Змінено тип
    {
        return $this->createQueryBuilder('l')
            ->where('l.user = :user')
            ->andWhere('l.articleComment = :comment')
            ->setParameter('user', $user)
            ->setParameter('comment', $comment)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // Альтернативний метод (для сумісності)
    public function findUserVoteForComment(User $user, ArticleComment $comment): ?Like
    {
        return $this->findUserVoteForArticleComment($user, $comment);
    }

    public function countLikesForComment(int $commentId): int
    {
        return $this->countLikesForArticleComment($commentId);
    }

    public function countDislikesForComment(int $commentId): int
    {
        return $this->countDislikesForArticleComment($commentId);
    }

    // ========== МЕТОДИ ДЛЯ БЛОГ ПОСТІВ ==========

    /**
     * Підрахунок лайків для блог поста
     */
    public function countLikesForBlogPost(int $blogPostId): int
    {
        return $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.blogPost = :blogPostId')
            ->andWhere('l.isLike = true')
            ->setParameter('blogPostId', $blogPostId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Підрахунок дизлайків для блог поста
     */
    public function countDislikesForBlogPost(int $blogPostId): int
    {
        return $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.blogPost = :blogPostId')
            ->andWhere('l.isLike = false')
            ->setParameter('blogPostId', $blogPostId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Знайти голос користувача для блог поста
     */
    public function findUserVoteForBlogPost(User $user, $blogPost): ?Like
    {
        return $this->findOneBy([
            'user' => $user,
            'blogPost' => $blogPost
        ]);
    }

    // ========== ЗАГАЛЬНІ МЕТОДИ ==========

    /**
     * Отримати топ статей за лайками
     */
    public function findTopArticles(int $limit = 10): array
    {
        return $this->createQueryBuilder('l')
            ->select('a.id, a.title, a.slug, COUNT(l.id) as likeCount')
            ->innerJoin('l.article', 'a')
            ->andWhere('l.isLike = true')
            ->andWhere('a.status = :published')
            ->setParameter('published', Article::STATUS_PUBLISHED)
            ->groupBy('a.id')
            ->orderBy('likeCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Видалити голос користувача
     */
    public function removeUserVote(Like $like): void
    {
        $this->getEntityManager()->remove($like);
        $this->getEntityManager()->flush();
    }

    /**
     * Отримати всі голоси користувача
     */
    public function findUserVotes(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }

}