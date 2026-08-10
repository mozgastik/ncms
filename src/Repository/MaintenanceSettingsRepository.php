<?php


namespace App\Repository;

use App\Entity\System\MaintenanceSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MaintenanceSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MaintenanceSettings::class);
    }

    public function getSettings(): MaintenanceSettings
    {
        $settings = $this->findOneBy([]);
        
        if (!$settings) {
            $settings = new MaintenanceSettings();
        }
        
        return $settings;
    }

    public function findRecentLogs(int $limit = 100): array
    {
        // Якщо логи зберігаються в окремій таблиці
        return $this->createQueryBuilder('m')
            ->orderBy('m.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function clearLogs(): void
    {
        // Очищення логів
        $this->createQueryBuilder('m')
            ->delete()
            ->getQuery()
            ->execute();
    }
}