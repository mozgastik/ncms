<?php
// src/Repository/ArticleImageRepository.php

namespace App\Repository;

use App\Entity\Article\ArticleImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ArticleImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleImage::class);
    }

    public function findUnusedImages(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.article IS NULL')
            ->orderBy('i.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findImagesByArticle($articleId): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.article = :articleId')
            ->setParameter('articleId', $articleId)
            ->orderBy('i.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}