<?php
// src/Twig/CategoryExtension.php

namespace App\Twig;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\CategoryManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CategoryExtension extends AbstractExtension
{
    public function __construct(
        private readonly CategoryRepository $repository,
        private readonly CategoryManager $categoryManager
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('category_tree', $this->getCategoryTree(...)),
            new TwigFunction('category_navigation', $this->getCategoryNavigation(...)),
            new TwigFunction('category_path', $this->getCategoryPath(...)),
            new TwigFunction('category_breadcrumbs', $this->getBreadcrumbs(...)),
        ];
    }

    /**
     * @param ?string $type
     * @return array
     */
    public function getCategoryTree(?string $type = null): array
    {
        return $this->categoryManager->getTree($type);
    }

    /**
     * @param ?string $type
     * @return array
     */
    public function getCategoryNavigation(?string $type = null): array
    {
        return $this->categoryManager->getNavigation($type);
    }

    /**
     * @param Category|int|null $category
     * @return array
     */
    public function getCategoryPath(Category|int|null $category): array
    {
        if (is_numeric($category)) {
            $category = $this->repository->find($category);
        }

        return $category instanceof Category ? $this->categoryManager->getPath($category) : [];
    }

    /**
     * @param Category|int|null $category
     * @return string
     */
    public function getBreadcrumbs(Category|int|null $category): string
    {
        $path = $this->getCategoryPath($category);
        $breadcrumbs = [];

        foreach ($path as $item) {
            $breadcrumbs[] = sprintf(
                '<a href="%s" class="breadcrumb-item">%s</a>',
                $this->generateCategoryUrl($item),
                $item->getName()
            );
        }

        return implode(' / ', $breadcrumbs);
    }

    private function generateCategoryUrl(Category $category): string
    {
        // Тут має бути генерація URL через router
        return '/category/' . $category->getSlug();
    }
}