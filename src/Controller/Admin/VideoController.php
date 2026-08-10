<?php
// src/Controller/Admin/VideoController.php

namespace App\Controller\Admin;

use App\Entity\System\Video;
use App\Form\VideoType;
use App\Repository\VideoRepository;
use App\Service\VideoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/videos')]
#[IsGranted('ROLE_ADMIN')]
class VideoController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VideoService $videoService
    ) {}

    #[Route('/', name: 'admin_video_index', methods: ['GET'])]
    public function index(VideoRepository $videoRepository, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 20;

        $videos = $videoRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            $limit,
            ($page - 1) * $limit
        );

        $total = $videoRepository->count([]);

        return $this->render('admin/video/index.html.twig', [
            'videos' => $videos,
            'currentPage' => $page,
            'totalPages' => ceil($total / $limit),
        ]);
    }

    #[Route('/new', name: 'admin_video_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $video = new Video();
        $video->setAuthor($this->getUser());

        $form = $this->createForm(VideoType::class, $video);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Отримати метадані
            $metadata = $this->videoService->fetchVideoMetadata($video);
            
            if (!empty($metadata)) {
                if (!$video->getTitle() && isset($metadata['title'])) {
                    $video->setTitle($metadata['title']);
                }
                if (!$video->getDescription() && isset($metadata['description'])) {
                    $video->setDescription($metadata['description']);
                }
                if (isset($metadata['duration'])) {
                    $video->setDuration($metadata['duration']);
                }
                if (isset($metadata['tags'])) {
                    $video->setTags($metadata['tags']);
                }
            }

            $this->entityManager->persist($video);
            $this->entityManager->flush();

            $this->addFlash('success', 'Відео успішно додано');
            return $this->redirectToRoute('admin_video_index');
        }

        return $this->render('admin/video/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_video_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Video $video): Response
    {
        $form = $this->createForm(VideoType::class, $video);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Відео оновлено');

            return $this->redirectToRoute('admin_video_index');
        }

        return $this->render('admin/video/edit.html.twig', [
            'video' => $video,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_video_delete', methods: ['POST'])]
    public function delete(Request $request, Video $video): Response
    {
        if ($this->isCsrfTokenValid('delete' . $video->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($video);
            $this->entityManager->flush();
            $this->addFlash('success', 'Відео видалено');
        }

        return $this->redirectToRoute('admin_video_index');
    }

    #[Route('/{id}/toggle-featured', name: 'admin_video_toggle_featured', methods: ['POST'])]
    public function toggleFeatured(Video $video): Response
    {
        $video->setIsFeatured(!$video->isFeatured());
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'featured' => $video->isFeatured(),
        ]);
    }

    #[Route('/export', name: 'admin_video_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
    // Отримуємо параметри фільтрації
    $search = $request->query->get('search');
    $status = $request->query->get('status');
    $source = $request->query->get('source');
    
    // Будуємо запит з фільтрами
    $qb = $this->entityManager->getRepository(Video::class)->createQueryBuilder('v');
    
    if ($search) {
        $qb->andWhere('v.title LIKE :search OR v.description LIKE :search')
           ->setParameter('search', '%' . $search . '%');
    }
    
    if ($status === 'published') {
        $qb->andWhere('v.isPublished = true');
    } elseif ($status === 'draft') {
        $qb->andWhere('v.isPublished = false');
    }
    
    if ($source) {
        $qb->andWhere('v.source = :source')
           ->setParameter('source', $source);
    }
    
    $videos = $qb->orderBy('v.createdAt', 'DESC')->getQuery()->getResult();
    
    // Створюємо CSV файл
    $csvData = [];
    
    // Заголовки колонок
    $csvData[] = [
        'ID',
        'Назва',
        'Опис',
        'URL',
        'Джерело',
        'Тривалість',
        'Перегляди',
        'Лайки',
        'Статус',
        'Рекомендоване',
        'Категорія',
        'Теги',
        'Дата створення',
        'Дата публікації'
    ];
    
    // Дані
    foreach ($videos as $video) {
        $csvData[] = [
            $video->getId(),
            $video->getTitle(),
            strip_tags($video->getDescription()),
            $video->getUrl(),
            $video->getSource(),
            $video->getFormattedDuration(),
            $video->getViews(),
            $video->getLikes(),
            $video->isPublished() ? 'Опубліковано' : 'Чернетка',
            $video->isFeatured() ? 'Так' : 'Ні',
            $video->getCategory() ? $video->getCategory()->getName() : '',
            $video->getTags(),
            $video->getCreatedAt() ? $video->getCreatedAt()->format('d.m.Y H:i') : '',
            $video->getPublishedAt() ? $video->getPublishedAt()->format('d.m.Y H:i') : '',
        ];
    }
    
    // Створюємо тимчасовий файл
    $handle = fopen('php://temp', 'r+');
    foreach ($csvData as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    
    // Отримуємо вміст
    $content = stream_get_contents($handle);
    fclose($handle);
    
    // Створюємо відповідь з CSV файлом
    $response = new Response($content);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="videos_export_' . date('Y-m-d_His') . '.csv"');
    
    return $response;
}
}