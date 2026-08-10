<?php
// src/Controller/Front/CategoryController.php

namespace App\Controller\Front;

use App\Entity\Article\Category;
use App\Repository\ArticleRepository;
use App\Repository\BlogPostRepository;
use App\Repository\CategoryRepository;
use App\Repository\VideoRepository;
use App\Service\CategoryManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/category')]
class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryManager $categoryManager
    ) {}

    #[Route('/', name: 'app_category_index', methods: ['GET'])]
    public function index(CategoryRepository $repository): Response
    {
        $categories = $repository->findRootCategories();

        return $this->render('components/front/category/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/{slug}', name: 'app_category_show', methods: ['GET'])]
    public function show(
        string $slug, 
        CategoryRepository $repository,
        ArticleRepository $articleRepository,
        BlogPostRepository $blogRepository,
        VideoRepository $videoRepository,
        Request $request
    ): Response {
        $category = $repository->findBySlug($slug);
        
        if (!$category) {
            throw $this->createNotFoundException('Категорія не знайдена');
        }

        // Отримуємо всіх нащадків для пошуку контенту
        $descendants = $this->categoryManager->getDescendants($category);
        $categoryIds = array_merge([$category->getId()], array_map(fn($c) => $c->getId(), $descendants));

        $page = $request->query->getInt('page', 1);
        $limit = 12;

        // Отримуємо контент залежно від типу категорії
        $items = [];
        $total = 0;

        switch ($category->getType()) {
            case Category::TYPE_ARTICLE:
                $items = $articleRepository->findByCategories($categoryIds, $page, $limit);
                $total = $articleRepository->countByCategories($categoryIds);
                break;
            case Category::TYPE_BLOG:
                $items = $blogRepository->findByCategories($categoryIds, $page, $limit);
                $total = $blogRepository->countByCategories($categoryIds);
                break;
            case Category::TYPE_VIDEO:
                $items = $videoRepository->findByCategories($categoryIds, $page, $limit);
                $total = $videoRepository->countByCategories($categoryIds);
                break;
            case Category::TYPE_ALL:
                // Змішаний контент
                $articles = $articleRepository->findByCategories($categoryIds, 1, 4);
                $blogs = $blogRepository->findByCategories($categoryIds, 1, 4);
                $videos = $videoRepository->findByCategories($categoryIds, 1, 4);
                $items = array_merge($articles, $blogs, $videos);
                usort($items, fn($a, $b) => $b->getPublishedAt() <=> $a->getPublishedAt());
                $total = count($items);
                break;
        }

        // Отримуємо підкатегорії
        $children = $category->getChildren();

        // Отримуємо популярні категорії
        $popularCategories = $repository->findPopular(5);

        return $this->render('components/front/category/show.html.twig', [
            'category' => $category,
            'items' => $items,
            'children' => $children,
            'popularCategories' => $popularCategories,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'path' => $this->categoryManager->getPath($category),
        ]);
    }

    #[Route('/{slug}/feed', name: 'app_category_feed', methods: ['GET'])]
    public function feed(string $slug, CategoryRepository $repository): Response
    {
        $category = $repository->findBySlug($slug);
        
        if (!$category) {
            throw $this->createNotFoundException('Категорія не знайдена');
        }

        // Генерація RSS/Atom feed
        $response = new Response();
        $response->headers->set('Content-Type', 'application/rss+xml');

        return $this->render('components/front/category/feed.xml.twig', [
            'category' => $category,
        ], $response);
    }
}