<?php
// src/Repository/AdZoneRepository.php
namespace App\Repository;

use App\Entity\Admin\AdZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AdZoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, AdZone::class); }
}