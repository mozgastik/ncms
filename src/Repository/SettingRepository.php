<?php

namespace App\Repository;

use App\Entity\Admin\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;

class SettingRepository extends ServiceEntityRepository
{
    private const CACHE_KEY_ALL = 'app_settings_all';
    private const CACHE_KEY_PUBLIC = 'app_settings_public';
    private const CACHE_KEY_GROUP = 'app_settings_group_';
    private const CACHE_KEY_SINGLE = 'app_settings_single_';

    public function __construct(
        ManagerRegistry $registry,
        private readonly CacheItemPoolInterface $cache
    ) {
        parent::__construct($registry, Setting::class);
    }

    public function getAllAsArray(bool $includeSystem = false): array
    {
        $cacheKey = self::CACHE_KEY_ALL . ($includeSystem ? '_system' : '');
        
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $qb = $this->createQueryBuilder('s')
            ->where('s.isVisible = :visible')
            ->setParameter('visible', true)
            ->orderBy('s.settingGroup', 'ASC')
            ->addOrderBy('s.sortOrder', 'ASC');

        if (!$includeSystem) {
            $qb->andWhere('s.isSystem = :system')
               ->setParameter('system', false);
        }

        $settings = $qb->getQuery()->getResult();

        $data = [];
        foreach ($settings as $setting) {
            $data[$setting->getSettingKey()] = $setting->getNormalizedValue();
        }

        $cacheItem->set($data);
        $cacheItem->expiresAfter(3600);
        $this->cache->save($cacheItem);

        return $data;
    }

    public function getPublicSettings(): array
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY_PUBLIC);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $settings = $this->createQueryBuilder('s')
            ->where('s.isPublic = :public')
            ->andWhere('s.isVisible = :visible')
            ->setParameter('public', true)
            ->setParameter('visible', true)
            ->orderBy('s.settingGroup', 'ASC')
            ->addOrderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($settings as $setting) {
            $data[$setting->getSettingKey()] = [
                'value' => $setting->getNormalizedValue(),
                'label' => $setting->getLabel(),
                'type' => $setting->getType(),
                'icon' => $setting->getIcon(),
            ];
        }

        $cacheItem->set($data);
        $cacheItem->expiresAfter(3600);
        $this->cache->save($cacheItem);

        return $data;
    }

    public function getGroupedSettings(): array
    {
        $settings = $this->findBy(['isVisible' => true], ['sortOrder' => 'ASC']);
        
        $grouped = [];
        foreach ($settings as $setting) {
            $group = $setting->getSettingGroup();
            if (!isset($grouped[$group])) {
                $grouped[$group] = [
                    'name' => $this->getGroupLabel($group),
                    'settings' => []
                ];
            }
            $grouped[$group]['settings'][] = $setting;
        }

        return $grouped;
    }

    public function getByGroup(string $group): array
    {
        $cacheKey = self::CACHE_KEY_GROUP . $group;
        
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $settings = $this->findBy(
            ['settingGroup' => $group, 'isVisible' => true],
            ['sortOrder' => 'ASC']
        );

        $cacheItem->set($settings);
        $cacheItem->expiresAfter(3600);
        $this->cache->save($cacheItem);

        return $settings;
    }

    public function findByKey(string $key): ?Setting
    {
        $cacheKey = self::CACHE_KEY_SINGLE . $key;
        
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $setting = $this->findOneBy(['settingKey' => $key]);

        if ($setting) {
            $cacheItem->set($setting);
            $cacheItem->expiresAfter(3600);
            $this->cache->save($cacheItem);
        }

        return $setting;
    }

    public function clearCache(): void
    {
        $keys = [
            self::CACHE_KEY_ALL,
            self::CACHE_KEY_ALL . '_system',
            self::CACHE_KEY_PUBLIC,
        ];

        foreach ($keys as $key) {
            $this->cache->deleteItem($key);
        }

        $groups = $this->createQueryBuilder('s')
            ->select('DISTINCT s.settingGroup')
            ->getQuery()
            ->getSingleColumnResult();

        foreach ($groups as $group) {
            $this->cache->deleteItem(self::CACHE_KEY_GROUP . $group);
        }

        $settings = $this->findAll();
        foreach ($settings as $setting) {
            $this->cache->deleteItem(self::CACHE_KEY_SINGLE . $setting->getSettingKey());
        }
    }

    private function getGroupLabel(string $group): string
    {
        return match ($group) {
            'general' => 'Загальні',
            'appearance' => 'Зовнішній вигляд',
            'social' => 'Соціальні мережі',
            'seo' => 'SEO',
            'system' => 'Системні',
            'mail' => 'Пошта',
            'security' => 'Безпека',
            'api' => 'API',
            default => ucfirst($group),
        };
    }
}