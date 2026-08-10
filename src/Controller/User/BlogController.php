<?php

namespace App\Controller\User;

use App\Entity\Blog\BlogPost;
use App\Entity\Blog\BlogImage;
use App\Form\BlogPostType;
use App\Repository\BlogPostRepository;
use App\Service\ReadingTimeCalculator;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/user/blog')]
class BlogController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BlogPostRepository $blogPostRepository,
        private ReadingTimeCalculator $readingTimeCalculator,
        private SluggerInterface $slugger,
        private ImageUploader $imageUploader
    ) {}

    /**
     * Список блогів користувача
     */
    #[Route('/', name: 'user_blog_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        $posts = $this->blogPostRepository->findBy(
            ['author' => $user],
            ['createdAt' => 'DESC']
        );

        // Статистика користувача
        $stats = [
            'total' => count($posts),
            'published' => count(array_filter($posts, fn($p) => $p->getStatus() === BlogPost::STATUS_PUBLISHED)),
            'pending' => count(array_filter($posts, fn($p) => $p->getStatus() === BlogPost::STATUS_PENDING)),
            'drafts' => count(array_filter($posts, fn($p) => $p->getStatus() === BlogPost::STATUS_DRAFT)),
            'rejected' => count(array_filter($posts, fn($p) => $p->getStatus() === BlogPost::STATUS_REJECTED)),
        ];

        return $this->render('user/blog/index.html.twig', [
            'posts' => $posts,
            'stats' => $stats,
        ]);
    }

    /**
     * Створення нового блогу
     */
    #[Route('/new', name: 'user_blog_new')]
    public function new(Request $request): Response
    {
        $blogPost = new BlogPost();
        $blogPost->setAuthor($this->getUser());
        $blogPost->setStatus(BlogPost::STATUS_DRAFT);
        
        $form = $this->createForm(BlogPostType::class, $blogPost);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Генеруємо slug з заголовка
            $slug = $this->slugger->slug($blogPost->getTitle())->lower();
            $blogPost->setSlug($slug . '-' . uniqid());
            
            // Розраховуємо час читання
            $readingTime = $this->readingTimeCalculator->calculate(
                $blogPost->getContent()
            );
            $blogPost->setReadingTime($readingTime);
            
            // Обробка завантажених зображень
            $this->handleImageUploads($form, $blogPost);
            
            // Якщо користувач відправив на модерацію
            if ($request->request->get('submit_type') === 'moderate') {
                $blogPost->setStatus(BlogPost::STATUS_PENDING);
                $this->addFlash('success', 'Блог відправлено на модерацію. Ми повідомимо, коли він буде опублікований.');
            } else {
                $blogPost->setStatus(BlogPost::STATUS_DRAFT);
                $this->addFlash('success', 'Чернетку збережено.');
            }
            
            $this->entityManager->persist($blogPost);
            $this->entityManager->flush();

            return $this->redirectToRoute('user_blog_index');
        }

        return $this->render('user/blog/new.html.twig', [
            'form' => $form->createView(),
            'blogPost' => $blogPost,
        ]);
    }

    /**
     * Редагування блогу
     */
    #[Route('/{id}/edit', name: 'user_blog_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, BlogPost $blogPost): Response
    {
        // Перевірка чи користувач є автором
        if ($blogPost->getAuthor() !== $this->getUser()) {
            $this->addFlash('error', 'Ви не можете редагувати цей блог.');
            return $this->redirectToRoute('user_blog_index');
        }

        $form = $this->createForm(BlogPostType::class, $blogPost);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Оновлюємо slug, якщо змінився заголовок
            $newSlug = $this->slugger->slug($blogPost->getTitle())->lower();
            if (!$blogPost->getSlug() || !str_starts_with($blogPost->getSlug(), $newSlug)) {
                $blogPost->setSlug($newSlug . '-' . uniqid());
            }
            
            // Оновлюємо час читання
            $readingTime = $this->readingTimeCalculator->calculate(
                $blogPost->getContent()
            );
            $blogPost->setReadingTime($readingTime);
            
            // Обробка завантажених зображень
            $this->handleImageUploads($form, $blogPost);
            
            // Визначаємо статус
            $oldStatus = $blogPost->getStatus();
            if ($request->request->get('submit_type') === 'moderate') {
                $blogPost->setStatus(BlogPost::STATUS_PENDING);
                if ($oldStatus !== BlogPost::STATUS_PENDING) {
                    $this->addFlash('success', 'Блог відправлено на модерацію.');
                }
            } elseif ($request->request->get('submit_type') === 'draft') {
                $blogPost->setStatus(BlogPost::STATUS_DRAFT);
                $this->addFlash('success', 'Зміни збережено в чернетці.');
            }
            
            $this->entityManager->flush();

            return $this->redirectToRoute('user_blog_index');
        }

        return $this->render('user/blog/edit.html.twig', [
            'form' => $form->createView(),
            'blogPost' => $blogPost,
        ]);
    }

    /**
     * Перегляд блогу (тільки для автора)
     */
    #[Route('/{id}', name: 'user_blog_show', methods: ['GET'])]
    public function show(BlogPost $blogPost): Response
    {
        // Перевірка чи користувач є автором
        if ($blogPost->getAuthor() !== $this->getUser()) {
            $this->addFlash('error', 'Ви не можете переглядати цей блог.');
            return $this->redirectToRoute('user_blog_index');
        }

        return $this->render('user/blog/show.html.twig', [
            'blogPost' => $blogPost,
        ]);
    }

    /**
     * Видалення блогу
     */
    #[Route('/{id}', name: 'user_blog_delete', methods: ['POST'])]
    public function delete(Request $request, BlogPost $blogPost): Response
    {
        // Перевірка чи користувач є автором
        if ($blogPost->getAuthor() !== $this->getUser()) {
            $this->addFlash('error', 'Ви не можете видалити цей блог.');
            return $this->redirectToRoute('user_blog_index');
        }

        if ($this->isCsrfTokenValid('delete' . $blogPost->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($blogPost);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Блог успішно видалено.');
        }

        return $this->redirectToRoute('user_blog_index');
    }

    /**
     * Зміна статусу блогу (швидкі дії)
     */
    #[Route('/{id}/status/{status}', name: 'user_blog_status', methods: ['POST'])]
    public function changeStatus(BlogPost $blogPost, string $status): Response
    {
        if ($blogPost->getAuthor() !== $this->getUser()) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $validStatuses = [
            BlogPost::STATUS_DRAFT,
            BlogPost::STATUS_PENDING,
        ];

        if (!in_array($status, $validStatuses)) {
            return $this->json(['error' => 'Invalid status'], 400);
        }

        $blogPost->setStatus($status);
        
        if ($status === BlogPost::STATUS_PENDING) {
            $blogPost->setPublishedAt(null); // Скидаємо дату публікації
        }
        
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'status' => $status,
            'message' => 'Статус змінено на: ' . $this->getStatusLabel($status),
        ]);
    }

    /**
     * Чернетки користувача
     */
    #[Route('/drafts', name: 'user_blog_drafts')]
    public function drafts(): Response
    {
        $user = $this->getUser();
        
        $drafts = $this->blogPostRepository->findBy([
            'author' => $user,
            'status' => BlogPost::STATUS_DRAFT,
        ], ['updatedAt' => 'DESC']);

        return $this->render('user/blog/drafts.html.twig', [
            'drafts' => $drafts,
        ]);
    }

    /**
     * Блоги на модерації
     */
    #[Route('/pending', name: 'user_blog_pending')]
    public function pending(): Response
    {
        $user = $this->getUser();
        
        $pending = $this->blogPostRepository->findBy([
            'author' => $user,
            'status' => BlogPost::STATUS_PENDING,
        ], ['updatedAt' => 'DESC']);

        return $this->render('user/blog/pending.html.twig', [
            'pending' => $pending,
        ]);
    }

    /**
     * Опубліковані блоги користувача
     */
    #[Route('/published', name: 'user_blog_published')]
    public function published(): Response
    {
        $user = $this->getUser();
        
        $published = $this->blogPostRepository->findBy([
            'author' => $user,
            'status' => BlogPost::STATUS_PUBLISHED,
        ], ['publishedAt' => 'DESC']);

        return $this->render('user/blog/published.html.twig', [
            'published' => $published,
        ]);
    }

    /**
     * Відхилені блоги
     */
    #[Route('/rejected', name: 'user_blog_rejected')]
    public function rejected(): Response
    {
        $user = $this->getUser();
        
        $rejected = $this->blogPostRepository->findBy([
            'author' => $user,
            'status' => BlogPost::STATUS_REJECTED,
        ], ['updatedAt' => 'DESC']);

        return $this->render('user/blog/rejected.html.twig', [
            'rejected' => $rejected,
        ]);
    }

    /**
     * Завантаження зображення (AJAX)
     */
    #[Route('/upload-image', name: 'user_blog_upload_image', methods: ['POST'])]
    public function uploadImage(Request $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('file');
        
        if (!$file) {
            return $this->json(['error' => 'Файл не завантажено'], 400);
        }

        // Перевірка типу файлу
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedTypes)) {
            return $this->json(['error' => 'Дозволені тільки зображення (JPEG, PNG, GIF, WebP)'], 400);
        }

        // Перевірка розміру
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file->getSize() > $maxSize) {
            return $this->json(['error' => 'Розмір файлу не повинен перевищувати 5MB'], 400);
        }

        try {
            $filename = $this->imageUploader->upload($file);
            
            return $this->json([
                'success' => true,
                'filename' => $filename,
                'url' => '/uploads/blogs/' . $filename,
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Помилка завантаження: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Видалення зображення (AJAX)
     */
    #[Route('/image/{id}/delete', name: 'user_blog_delete_image', methods: ['DELETE'])]
    public function deleteImage(BlogImage $blogImage): JsonResponse
    {
        // Перевірка чи користувач є автором блогу
        if ($blogImage->getBlogPost()->getAuthor() !== $this->getUser()) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        try {
            $this->entityManager->remove($blogImage);
            $this->entityManager->flush();
            
            // Видалити фізичний файл
            $filePath = $this->getParameter('blog_upload_directory') . '/' . $blogImage->getFilename();
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Помилка видалення'], 500);
        }
    }

    /**
     * Обробка завантажених зображень
     */
    private function handleImageUploads($form, BlogPost $blogPost): void
    {
        // Обробка головного зображення
        $featuredImageFile = $form->get('featuredImage')->getData();
        if ($featuredImageFile) {
            $filename = $this->imageUploader->upload($featuredImageFile);
            
            // Створюємо нове зображення
            $image = new BlogImage();
            $image->setFilename($filename);
            $image->setPath('/uploads/blogs/' . $filename);
            $image->setBlogPost($blogPost);
            $image->setSortOrder(0);
            
            $blogPost->addImage($image);
        }

        // Обробка додаткових зображень
        $additionalImages = $form->get('additionalImages')->getData();
        if ($additionalImages) {
            $sortOrder = 1;
            foreach ($additionalImages as $imageFile) {
                if ($imageFile) {
                    $filename = $this->imageUploader->upload($imageFile);
                    
                    $image = new BlogImage();
                    $image->setFilename($filename);
                    $image->setPath('/uploads/blogs/' . $filename);
                    $image->setBlogPost($blogPost);
                    $image->setSortOrder($sortOrder++);
                    
                    $blogPost->addImage($image);
                }
            }
        }
    }

    /**
     * Отримання текстового представлення статусу
     */
    private function getStatusLabel(string $status): string
    {
        return match($status) {
            BlogPost::STATUS_DRAFT => 'Чернетка',
            BlogPost::STATUS_PENDING => 'На модерації',
            BlogPost::STATUS_PUBLISHED => 'Опубліковано',
            BlogPost::STATUS_REJECTED => 'Відхилено',
            default => 'Невідомо',
        };
    }
}