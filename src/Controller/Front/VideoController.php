<?php
// src/Controller/Front/VideoController.php

namespace App\Controller\Front;

use App\Entity\System\Video;
use App\Repository\VideoRepository;
use App\Repository\CategoryRepository;
use App\Service\VideoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/video')]
class VideoController extends AbstractController
{
    public function __construct(
        private readonly VideoService $videoService
    ) {}

    // src/Controller/Front/VideoController.php

#[Route('/', name: 'app_video_index', methods: ['GET'])]
public function index(Request $request, VideoRepository $videoRepository, CategoryRepository $categoryRepository): Response
{
    $search = $request->query->get('search');
    $sort = $request->query->get('sort', 'newest');
    $page = $request->query->getInt('page', 1);
    $limit = 12;
    
    // Отримуємо категорії
    $categories = $categoryRepository->findAll();
    
    // Будуємо запит з фільтрами
    $qb = $videoRepository->createQueryBuilder('v')
        ->where('v.isPublished = true')
        ->orderBy('v.publishedAt', 'DESC');
    
    if ($search) {
        $qb->andWhere('v.title LIKE :search OR v.description LIKE :search')
           ->setParameter('search', '%' . $search . '%');
    }
    
    switch ($sort) {
        case 'oldest':
            $qb->orderBy('v.publishedAt', 'ASC');
            break;
        case 'popular':
            $qb->orderBy('v.views', 'DESC');
            break;
        case 'views':
            $qb->orderBy('v.views', 'DESC');
            break;
        default:
            $qb->orderBy('v.publishedAt', 'DESC');
    }
    
    // Пагінація
    $total = count($qb->getQuery()->getResult());
    $videos = $qb->setFirstResult(($page - 1) * $limit)
                 ->setMaxResults($limit)
                 ->getQuery()
                 ->getResult();
    
    return $this->render('components/front/video/index.html.twig', [
        'videos' => $videos,
        'categories' => $categories,
        'totalPages' => ceil($total / $limit),
        'currentPage' => $page,
    ]);
}

    #[Route('/{id}', name: 'app_video_show', methods: ['GET'])]
    public function show(Video $video, VideoRepository $videoRepository): Response
    {
        // Перевірка доступу
        if (!$video->isPublished()) {
            throw $this->createNotFoundException('Відео не знайдено');
        }

        // Оновити перегляди
        $this->videoService->trackView($video);

        // Отримати рекомендовані відео
        $recommended = $this->videoService->getRelatedVideos($video);

        return $this->render('components/front/video/show.html.twig', [
            'video' => $video,
            'recommended' => $recommended,
        ]);
    }

    // src/Controller/Front/VideoController.php

#[Route('/category/{id}', name: 'app_video_category', methods: ['GET'])]
public function category(int $id, Request $request, VideoRepository $videoRepository, CategoryRepository $categoryRepository): Response
{
    $category = $categoryRepository->find($id);
    
    if (!$category) {
        throw $this->createNotFoundException('Категорію не знайдено');
    }
    
    $search = $request->query->get('search');
    $sort = $request->query->get('sort', 'newest');
    $page = $request->query->getInt('page', 1);
    $limit = 12;
    
    // Будуємо запит для відео в цій категорії
    $qb = $videoRepository->createQueryBuilder('v')
        ->where('v.isPublished = true')
        ->andWhere('v.category = :category')
        ->setParameter('category', $category);
    
    if ($search) {
        $qb->andWhere('v.title LIKE :search OR v.description LIKE :search')
           ->setParameter('search', '%' . $search . '%');
    }
    
    switch ($sort) {
        case 'oldest':
            $qb->orderBy('v.publishedAt', 'ASC');
            break;
        case 'popular':
            $qb->orderBy('v.views', 'DESC');
            break;
        default:
            $qb->orderBy('v.publishedAt', 'DESC');
    }
    
    // Пагінація
    $total = count($qb->getQuery()->getResult());
    $videos = $qb->setFirstResult(($page - 1) * $limit)
                 ->setMaxResults($limit)
                 ->getQuery()
                 ->getResult();
    
    // Інші категорії (для блоку "Інші категорії")
    $otherCategories = $categoryRepository->createQueryBuilder('c')
        ->where('c.id != :id')
        ->setParameter('id', $category->getId())
        ->orderBy('c.name', 'ASC')
        ->getQuery()
        ->getResult();
    
    return $this->render('components/front/video/category.html.twig', [
        'category' => $category,
        'videos' => $videos,
        'otherCategories' => $otherCategories,
        'totalPages' => ceil($total / $limit),
        'currentPage' => $page,
    ]);
}

    #[Route('/search', name: 'app_video_search', methods: ['GET'])]
    public function search(Request $request, VideoRepository $videoRepository): Response
    {
        $query = $request->query->get('q', '');
        $videos = [];

        if ($query) {
            $videos = $videoRepository->search($query);
        }

        return $this->render('components/front/video/search.html.twig', [
            'videos' => $videos,
            'query' => $query,
        ]);
    }
}