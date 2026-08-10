<?php
// src/Service/CategoryManager.php

namespace App\Service;

use App\Entity\Article\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class CategoryManager
{
    private const CACHE_KEY_TREE = 'category_tree';
    private const CACHE_KEY_NAVIGATION = 'category_navigation';

    public function __construct(
        private readonly CategoryRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheItemPoolInterface $cache,
        private readonly SluggerInterface $slugger
    ) {}

    /**
     * Створити категорію
     */
    public function createCategory(Category $category, ?Category $parent = null): Category
    {
        if (!$category->getSlug()) {
            $category->setSlug($this->generateSlug($category->getName()));
        }

        if ($parent) {
            $category->setParent($parent);
        }

        $this->entityManager->persist($category);
        $this->entityManager->flush();
        
        $this->clearCache();

        return $category;
    }

    /**
     * Оновити категорію
     */
    public function updateCategory(Category $category): void
    {
        if (!$category->getSlug()) {
            $category->setSlug($this->generateSlug($category->getName()));
        }

        $this->entityManager->flush();
        $this->clearCache();
    }

    /**
     * Видалити категорію
     */
    public function deleteCategory(Category $category): void
    {
        // Переміщуємо дочірні категорії до батька
        if ($category->getParent()) {
            foreach ($category->getChildren() as $child) {
                $child->setParent($category->getParent());
            }
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();
        $this->clearCache();
    }

    /**
     * Отримати дерево категорій
     */
    public function getTree(string $type = null): array
    {
        $cacheKey = self::CACHE_KEY_TREE . ($type ? '_' . $type : '');
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $tree = $this->repository->getTree($type);
        
        $cacheItem->set($tree);
        $cacheItem->expiresAfter(3600); // 1 година
        $this->cache->save($cacheItem);

        return $tree;
    }

    /**
     * Отримати категорії для навігації
     */
    public function getNavigation(string $type = null): array
    {
        $cacheKey = self::CACHE_KEY_NAVIGATION . ($type ? '_' . $type : '');
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $navigation = $this->repository->findForNavigation($type);
        
        $cacheItem->set($navigation);
        $cacheItem->expiresAfter(3600);
        $this->cache->save($cacheItem);

        return $navigation;
    }

    /**
     * Генерувати слаг
     */
    public function generateSlug(string $name): string
    {
        $slug = $this->slugger->slug($name)->lower()->toString();
        
        // Перевіряємо унікальність
        $originalSlug = $slug;
        $counter = 1;
        
        while (!$this->repository->isSlugUnique($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Очистити кеш
     */
    public function clearCache(): void
    {
        $this->cache->deleteItem(self::CACHE_KEY_TREE);
        $this->cache->deleteItem(self::CACHE_KEY_NAVIGATION);
    }

    /**
     * Отримати шлях до категорії
     */
    public function getPath(Category $category): array
    {
        $path = [];
        $current = $category;

        while ($current) {
            array_unshift($path, $current);
            $current = $current->getParent();
        }

        return $path;
    }

    /**
     * Отримати всі нащадки категорії
     */
    public function getDescendants(Category $category): array
    {
        $descendants = [];
        $this->collectDescendants($category, $descendants);
        return $descendants;
    }

    private function collectDescendants(Category $category, array &$descendants): void
    {
        foreach ($category->getChildren() as $child) {
            $descendants[] = $child;
            $this->collectDescendants($child, $descendants);
        }
    }

    /**
     * Отримати кількість елементів в категорії
     */
    public function getItemsCount(Category $category): array
    {
        // Тут буде логіка підрахунку через QueryBuilder
        return [
            'articles' => 0,
            'blogs' => 0,
            'videos' => 0,
            'total' => 0,
        ];
    }
}