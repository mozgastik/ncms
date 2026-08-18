<?php

namespace App\Controller\Front;

use App\Entity\Article\Article;
use App\Entity\System\Video;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use App\Repository\LikeRepository;
use App\Repository\VideoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        ArticleRepository $articleRepository, 
        CategoryRepository $categoryRepository, 
        TagRepository $tagRepository,
        LikeRepository $likeRepository,
        VideoRepository $videoRepository
    ): Response
    {
        // Головні новини (останні опубліковані)
        $featuredArticles = $articleRepository->findBy(
            ['status' => Article::STATUS_PUBLISHED],
            ['publishedAt' => 'DESC'],
            2
        );
        
        // Останні новини
        $latestArticles = $articleRepository->findBy(
            ['status' => Article::STATUS_PUBLISHED],
            ['publishedAt' => 'DESC'],
            10
        );
        
        // Популярні новини (за переглядами)
        $popularArticles = $articleRepository->findBy(
            ['status' => Article::STATUS_PUBLISHED],
            ['views' => 'DESC'],
            5
        );
        
        // Рекомендовані новини (наприклад, з високим пріоритетом)
        $recommendedArticles = $articleRepository->findBy(
            ['status' => Article::STATUS_PUBLISHED],
            ['priority' => 'DESC', 'publishedAt' => 'DESC'],
            3
        );
        
        
        // ВІДЕО (виправлено!)
        $videos = $videoRepository->findBy(
            ['isPublished' => true],  // Використовуємо правильне поле з Video Entity
            ['publishedAt' => 'DESC'],
            6
        );
        
        // Теги
        $tags = $tagRepository->findBy([], [], 15);

        // Категорії
        $categories = $categoryRepository->findAll();

        // Додаткові дані для шаблону
        $todayCount = $articleRepository->countTodayArticles();
        $publishedCount = $articleRepository->count(['status' => Article::STATUS_PUBLISHED]);
        $topViewed = $articleRepository->findOneBy(['status' => Article::STATUS_PUBLISHED], ['views' => 'DESC']);
        $commentCount = 0; // Тут потрібно додати логіку підрахунку коментарів

        return $this->render('components/home/index.html.twig', [
            'featuredArticles' => $featuredArticles,
            'latestArticles' => $latestArticles,
            'popularArticles' => $popularArticles,
            'recommendedArticles' => $recommendedArticles,
            'tags' => $tags,
            'categories' => $categories,
            'todayCount' => $todayCount,
            'publishedCount' => $publishedCount,
            'topViewed' => $topViewed,
            'commentCount' => $commentCount,
            'likeRepository' => $likeRepository,
            'videos' => $videos,  // Змінено з 'video' на 'videos' (множина)
        ]);
    }

    // Додатковий метод для перегляду всіх відео
    #[Route('/video', name: 'app_video_index')]
    public function videoIndex(VideoRepository $videoRepository): Response
    {
        $videos = $videoRepository->findBy(
            ['isPublished' => true],
            ['publishedAt' => 'DESC']
        );

        return $this->render('video/index.html.twig', [
            'videos' => $videos,
        ]);
    }

    // Метод для перегляду одного відео
    #[Route('/video/{id}', name: 'app_video_show', requirements: ['id' => '\d+'])]
    public function videoShow(int $id, VideoRepository $videoRepository): Response
    {
        $video = $videoRepository->find($id);
        
        if (!$video || !$video->isPublished()) {
            throw $this->createNotFoundException('Відео не знайдено');
        }

        // Збільшуємо лічильник переглядів
        $video->setViews($video->getViews() + 1);
        $videoRepository->getEntityManager()->flush();

        // Отримуємо рекомендовані відео
        $recommended = $videoRepository->findBy(
            ['isPublished' => true],
            ['views' => 'DESC'],
            6
        );

        return $this->render('video/show.html.twig', [
            'video' => $video,
            'recommended' => $recommended,
        ]);
    }
}