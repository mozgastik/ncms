<?php
// src/Controller/UserVideoController.php

namespace App\Controller\User;

use App\Entity\System\Video;
use App\Form\UserVideoType;
use App\Repository\VideoRepository;
use App\Service\VideoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user/videos')]
#[IsGranted('ROLE_USER')]
class UserVideoController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VideoService $videoService
    ) {}

    #[Route('/', name: 'user_video_index', methods: ['GET'])]
    public function index(VideoRepository $videoRepository): Response
    {
        $user = $this->getUser();
        
        $videos = $videoRepository->findBy(
            ['author' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('user/video/index.html.twig', [
            'videos' => $videos,
            'stats' => [
                'total' => count($videos),
                'published' => count(array_filter($videos, fn($v) => $v->isPublished())),
                'drafts' => count(array_filter($videos, fn($v) => !$v->isPublished())),
                'views' => array_sum(array_map(fn($v) => $v->getViews(), $videos)),
            ]
        ]);
    }

    #[Route('/new', name: 'user_video_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $video = new Video();
        $video->setAuthor($this->getUser());

        $form = $this->createForm(UserVideoType::class, $video);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Автоматичне визначення джерела
            $video->parseVideoUrl();
            
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
                if (isset($metadata['thumbnail'])) {
                    $video->setThumbnail($metadata['thumbnail']);
                }
            }

            // Нові відео за замовчуванням не публікуються (потрібна модерація)
            $video->setIsPublished(false);
            
            $this->entityManager->persist($video);
            $this->entityManager->flush();

            $this->addFlash('success', 'Відео успішно додано! Воно буде опубліковано після перевірки модератором.');
            
            return $this->redirectToRoute('user_video_index');
        }

        return $this->render('user/video/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'user_video_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Video $video): Response
    {
        // Перевірка, чи це відео належить користувачу
        if ($video->getAuthor() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Це відео не належить вам');
        }

        $form = $this->createForm(UserVideoType::class, $video);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $video->parseVideoUrl();
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Відео оновлено');
            return $this->redirectToRoute('user_video_index');
        }

        return $this->render('user/video/edit.html.twig', [
            'video' => $video,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'user_video_delete', methods: ['POST'])]
    public function delete(Request $request, Video $video): Response
    {
        if ($video->getAuthor() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Це відео не належить вам');
        }

        if ($this->isCsrfTokenValid('delete' . $video->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($video);
            $this->entityManager->flush();
            $this->addFlash('success', 'Відео видалено');
        }

        return $this->redirectToRoute('user_video_index');
    }

    #[Route('/{id}/stats', name: 'user_video_stats', methods: ['GET'])]
    public function stats(Video $video): Response
    {
        if ($video->getAuthor() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Це відео не належить вам');
        }

        return $this->render('user/video/stats.html.twig', [
            'video' => $video,
        ]);
    }
}