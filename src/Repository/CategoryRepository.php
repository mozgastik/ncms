<?php
// src/Repository/CategoryRepository.php

namespace App\Repository;

use App\Entity\Article\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Отримати активні категорії
     */
    public function findActive(array $criteria = [], array $orderBy = ['sortOrder' => 'ASC', 'name' => 'ASC'], $limit = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->andWhere('c.isVisible = true');

        if (!empty($criteria)) {
            foreach ($criteria as $field => $value) {
                $qb->andWhere("c.$field = :$field")
                   ->setParameter($field, $value);
            }
        }

        foreach ($orderBy as $field => $direction) {
            $qb->addOrderBy("c.$field", $direction);
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Отримати кореневі категорії (без батька)
     */
    public function findRootCategories(string $type = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.parent IS NULL')
            ->andWhere('c.isActive = true')
            ->andWhere('c.isVisible = true')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC');

        if ($type) {
            $qb->andWhere('c.type = :type OR c.type = :all')
               ->setParameter('type', $type)
               ->setParameter('all', Category::TYPE_ALL);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Отримати категорії для навігації (з підрахунком елементів)
     */
    public function findForNavigation(string $type = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->andWhere('c.isVisible = true')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC');

        if ($type) {
            $qb->andWhere('c.type = :type OR c.type = :all')
               ->setParameter('type', $type)
               ->setParameter('all', Category::TYPE_ALL);
        }

        $categories = $qb->getQuery()->getResult();
        
        // Завантажуємо підрахунки для кожної категорії
        foreach ($categories as $category) {
            $this->loadCounts($category);
        }

        return $categories;
    }

    /**
     * Отримати категорію за слагом
     */
    public function findBySlug(string $slug): ?Category
    {
        return $this->findOneBy(['slug' => $slug, 'isActive' => true]);
    }

    /**
     * Отримати дерево категорій
     */
    public function getTree(string $type = null): array
    {
        $categories = $this->findRootCategories($type);
        return $this->buildTree($categories);
    }

    /**
     * Побудувати дерево категорій
     */
    private function buildTree(array $categories): array
    {
        $tree = [];
        foreach ($categories as $category) {
            $this->loadCounts($category);
            $node = [
                'category' => $category,
                'children' => $this->buildTree($category->getChildren()->toArray()),
            ];
            $tree[] = $node;
        }
        return $tree;
    }

    /**
     * Завантажити підрахунки для категорії
     */
    private function loadCounts(Category $category): void
    {
        // Тут буде логіка підрахунку через JOIN з іншими таблицями
        // Поки що залишаємо заглушку
    }

    /**
     * Пошук категорій
     */
    public function search(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->andWhere('c.name LIKE :query OR c.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Отримати популярні категорії
     */
    public function findPopular(int $limit = 5): array
    {
        // Тут буде логіка з підрахунком використання
        return $this->findBy(['isActive' => true], ['sortOrder' => 'ASC'], $limit);
    }

    /**
     * Отримати категорії для адмінки
     */
    public function getAdminList(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.parent', 'p')
            ->addSelect('p')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC');

        if (!empty($filters['search'])) {
            $qb->andWhere('c.name LIKE :search OR c.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('c.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['active'])) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', $filters['active']);
        }

        return $qb;
    }

    /**
     * Отримати кількість дочірніх категорій
     */
    public function getChildrenCount(Category $category): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.parent = :parent')
            ->setParameter('parent', $category)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Перевірити унікальність слага
     */
    public function isSlugUnique(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId) {
            $qb->andWhere('c.id != :id')
               ->setParameter('id', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() == 0;
    }
}