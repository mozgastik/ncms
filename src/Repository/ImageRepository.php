<?php

namespace App\Repository;

use App\Entity\System\Image;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Image>
 */
class ImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Image::class);
    }

    // Додайте власні методи тут
    
    public function findByArticle($articleId): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.article = :articleId')
            ->setParameter('articleId', $articleId)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}