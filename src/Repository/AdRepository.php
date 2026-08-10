<?php
// src/Repository/AdRepository.php
namespace App\Repository;

use App\Entity\Admin\Ad;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AdRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Ad::class); }

    /**
     * Повертає активні банери для заданої зони з урахуванням пріоритету
     */
    public function findActiveByZone(string $zoneCode): array
    {
        $now = new \DateTimeImmutable();
        return $this->createQueryBuilder('a')
            ->join('a.zone', 'z')
            ->andWhere('z.code = :code')->setParameter('code', $zoneCode)
            ->andWhere('z.isActive = :zoneActive')->setParameter('zoneActive', true)
            ->andWhere('a.isActive = :active')->setParameter('active', true)
            ->andWhere('a.startAt IS NULL OR a.startAt <= :now')->setParameter('now', $now)
            ->andWhere('a.endAt IS NULL OR a.endAt >= :now')
            ->orderBy('a.priority', 'DESC')
            ->getQuery()
            ->getResult();
    }
}