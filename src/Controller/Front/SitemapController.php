<?php

namespace App\Controller\Front;

use App\Repository\ArticleRepository;
use App\Repository\BlogPostRepository;
use App\Repository\CategoryRepository;
use App\Repository\PageRepository;
use App\Repository\TagRepository;
use App\Repository\VideoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'app_sitemap', defaults: ['_format' => 'xml'])]
    public function index(
        ArticleRepository $articleRepository,
        BlogPostRepository $blogRepository,
        VideoRepository $videoRepository,
        CategoryRepository $categoryRepository,
        TagRepository $tagRepository,
        PageRepository $pageRepository,
    ): Response {
        $urls = [];

        // Головна сторінка
        $urls[] = [
            'loc' => $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'changefreq' => 'hourly',
            'priority' => '1.0',
        ];

        // Статті
        $articles = $articleRepository->findBy(['status' => 'published']);
        foreach ($articles as $article) {
            $urls[] = [
                'loc' => $this->generateUrl('app_article_show', ['slug' => $article->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'daily',
                'priority' => '0.8',
                'lastmod' => $article->getUpdatedAt() ?? $article->getPublishedAt() ?? $article->getCreatedAt(),
            ];
        }

        // Блоги
        $blogs = $blogRepository->findBy(['status' => 'published']);
        foreach ($blogs as $blog) {
            $urls[] = [
                'loc' => $this->generateUrl('blog_show', ['slug' => $blog->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'daily',
                'priority' => '0.7',
                'lastmod' => $blog->getUpdatedAt() ?? $blog->getPublishedAt(),
            ];
        }

        // Відео
        $videos = $videoRepository->findBy(['isPublished' => true]);
        foreach ($videos as $video) {
            $urls[] = [
                'loc' => $this->generateUrl('app_video_show', ['id' => $video->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'weekly',
                'priority' => '0.6',
                'lastmod' => $video->getUpdatedAt() ?? $video->getPublishedAt(),
            ];
        }

        // Категорії
        $categories = $categoryRepository->findAll();
        foreach ($categories as $category) {
            $urls[] = [
                'loc' => $this->generateUrl('app_category_show', ['slug' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'weekly',
                'priority' => '0.6',
                'lastmod' => $category->getUpdatedAt(),
            ];
        }

        // Теги
        $tags = $tagRepository->findAll();
        foreach ($tags as $tag) {
            $urls[] = [
                'loc' => $this->generateUrl('app_tag_show', ['slug' => $tag->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'monthly',
                'priority' => '0.4',
                'lastmod' => $tag->getUpdatedAt(),
            ];
        }

        // Статичні сторінки
        $pages = $pageRepository->findBy(['isPublished' => true]);
        foreach ($pages as $page) {
            $urls[] = [
                'loc' => $this->generateUrl('app_page_show', ['slug' => $page->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'changefreq' => 'monthly',
                'priority' => '0.5',
                'lastmod' => $page->getUpdatedAt() ?? $page->getCreatedAt(),
            ];
        }

        // Генеруємо XML
        $response = $this->render('components/sitemap/sitemap.xml.twig', [
            'urls' => $urls,
        ]);
        $response->headers->set('Content-Type', 'application/xml; charset=utf-8');

        return $response;
    }
}