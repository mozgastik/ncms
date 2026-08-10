<?php

namespace App\Controller\Admin;

use App\Entity\Article\Article;
use App\Entity\Admin\Tag;
use App\Entity\Article\Category;
use App\Form\Admin\AdminArticleType;
use App\Repository\ArticleRepository;
use App\Repository\TagRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/admin/articles')]
#[IsGranted('ROLE_ADMIN')]
class ArticleController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private SerializerInterface $serializer;

    public function __construct(
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        SerializerInterface $serializer
    ) {
        $this->entityManager = $entityManager;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->serializer = $serializer;
    }

    /**
     * Список статей (сторінка)
     */
    #[Route('/', name: 'admin_article_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/article/index.html.twig');
    }

    /**
     * API для отримання списку статей з фільтрацією
     */
    #[Route('/api', name: 'admin_articles_api', methods: ['GET'])]
    public function api(Request $request, ArticleRepository $repository): JsonResponse
    {
        $status = $request->query->get('status');
        $search = $request->query->get('search');
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $sortBy = $request->query->get('sortBy', 'createdAt');
        $sortOrder = $request->query->get('sortOrder', 'DESC');

        $queryBuilder = $repository->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->leftJoin('a.author', 'u')
            ->addSelect('c', 'u');

        // Фільтр за статусом
        if ($status && in_array($status, [
            Article::STATUS_DRAFT,
            Article::STATUS_PENDING,
            Article::STATUS_APPROVED,
            Article::STATUS_PUBLISHED,
            Article::STATUS_REJECTED,
            Article::STATUS_ARCHIVED
        ])) {
            $queryBuilder->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        // Пошук
        if ($search) {
            $queryBuilder->andWhere('a.title LIKE :search OR a.excerpt LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Сортування
        $allowedSortFields = ['id', 'title', 'status', 'views', 'likeCount', 'commentCount', 'createdAt', 'publishedAt'];
        if (in_array($sortBy, $allowedSortFields)) {
            $queryBuilder->orderBy('a.' . $sortBy, $sortOrder);
        }

        // Підрахунок загальної кількості
        $totalCount = clone $queryBuilder;
        $totalItems = $totalCount->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Пагінація
        $offset = ($page - 1) * $limit;
        $articles = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Форматування даних з CSRF токенами
        $data = array_map(function(Article $article) {
            return [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'slug' => $article->getSlug(),
                'excerpt' => $article->getExcerpt(),
                'coverImage' => $article->getCoverImage(),
                'coverImageUrl' => $article->getCoverImageUrl(),
                'status' => $article->getStatus(),
                'statusLabel' => $this->getStatusLabel($article->getStatus()),
                'views' => $article->getViews(),
                'likeCount' => $article->getLikeCount(),
                'commentCount' => $article->getCommentCount(),
                'shareCount' => $article->getShareCount(),
                'readingTime' => $article->getReadingTime(),
                'isBreaking' => $article->isBreaking(),
                'isFeatured' => $article->isFeatured(),
                'isPinned' => $article->isPinned(),
                'createdAt' => $article->getCreatedAt()?->format('c'),
                'updatedAt' => $article->getUpdatedAt()?->format('c'),
                'publishedAt' => $article->getPublishedAt()?->format('c'),
                'category' => $article->getCategory() ? [
                    'id' => $article->getCategory()->getId(),
                    'name' => $article->getCategory()->getName(),
                    'slug' => $article->getCategory()->getSlug(),
                ] : null,
                'author' => $article->getAuthor() ? [
                    'id' => $article->getAuthor()->getId(),
                    'username' => $article->getAuthor()->getUsername(),
                    'avatar' => $article->getAuthor()->getAvatar(),
                ] : null,
                // CSRF токени для дій
                'changeStatusToken' => $this->csrfTokenManager->getToken('change-status' . $article->getId())->getValue(),
                'deleteToken' => $this->csrfTokenManager->getToken('delete' . $article->getId())->getValue(),
            ];
        }, $articles);

        return $this->json([
            'success' => true,
            'articles' => $data,
            'pagination' => [
                'currentPage' => $page,
                'itemsPerPage' => $limit,
                'totalItems' => (int) $totalItems,
                'totalPages' => ceil($totalItems / $limit),
            ],
            'filters' => [
                'status' => $status,
                'search' => $search,
                'sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
            ]
        ]);
    }

    /**
     * Отримання однієї статті (API)
     */
    #[Route('/{id}/api', name: 'admin_article_api_show', methods: ['GET'])]
    public function apiShow(Article $article): JsonResponse
    {
        $data = [
            'id' => $article->getId(),
            'title' => $article->getTitle(),
            'slug' => $article->getSlug(),
            'excerpt' => $article->getExcerpt(),
            'content' => $article->getContent(),
            'coverImage' => $article->getCoverImage(),
            'coverImageUrl' => $article->getCoverImageUrl(),
            'status' => $article->getStatus(),
            'statusLabel' => $this->getStatusLabel($article->getStatus()),
            'views' => $article->getViews(),
            'likeCount' => $article->getLikeCount(),
            'commentCount' => $article->getCommentCount(),
            'shareCount' => $article->getShareCount(),
            'readingTime' => $article->getReadingTime(),
            'isBreaking' => $article->isBreaking(),
            'isFeatured' => $article->isFeatured(),
            'isPinned' => $article->isPinned(),
            'priority' => $article->getPriority(),
            'createdAt' => $article->getCreatedAt()?->format('c'),
            'updatedAt' => $article->getUpdatedAt()?->format('c'),
            'publishedAt' => $article->getPublishedAt()?->format('c'),
            'metaTitle' => $article->getMetaTitle(),
            'metaDescription' => $article->getMetaDescription(),
            'metaKeywords' => $article->getMetaKeywords(),
            'moderatorNotes' => $article->getModeratorNotes(),
            'source' => $article->getSource(),
            'sourceUrl' => $article->getSourceUrl(),
            'category' => $article->getCategory() ? [
                'id' => $article->getCategory()->getId(),
                'name' => $article->getCategory()->getName(),
                'slug' => $article->getCategory()->getSlug(),
            ] : null,
            'author' => $article->getAuthor() ? [
                'id' => $article->getAuthor()->getId(),
                'username' => $article->getAuthor()->getUsername(),
                'avatar' => $article->getAuthor()->getAvatar(),
            ] : null,
            'moderator' => $article->getModerator() ? [
                'id' => $article->getModerator()->getId(),
                'username' => $article->getModerator()->getUsername(),
            ] : null,
            'changeStatusToken' => $this->csrfTokenManager->getToken('change-status' . $article->getId())->getValue(),
            'deleteToken' => $this->csrfTokenManager->getToken('delete' . $article->getId())->getValue(),
        ];

        return $this->json([
            'success' => true,
            'article' => $data
        ]);
    }

    /**
     * Створення нової статті
     */
    #[Route('/new', name: 'admin_article_new', methods: ['GET', 'POST'])]
    public function new(Request $request, TagRepository $tagRepository): Response
    {
        $article = new Article();
        $article->setAuthor($this->getUser());
        
        $form = $this->createForm(AdminArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Генерація slug
            if (!$article->getSlug()) {
                $article->setSlug($this->generateSlug($article->getTitle()));
            }
            
            $article->calculateReadingTime();
            $article->setCreatedAt(new \DateTime());
            $article->setUpdatedAt(new \DateTime());
            
            // Якщо статус опубліковано, встановлюємо дату публікації
            if ($article->getStatus() === Article::STATUS_PUBLISHED) {
                $article->setPublishedAt(new \DateTime());
            }
            
            $this->entityManager->persist($article);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Статтю створено');
            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/article/new.html.twig', [
            'form' => $form->createView(),
            'allTags' => $tagRepository->findAll(),
        ]);
    }

    /**
     * Редагування статті
     */
    #[Route('/{id}/edit', name: 'admin_article_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Article $article, TagRepository $tagRepository): Response
    {
        $form = $this->createForm(AdminArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Генерація slug якщо порожній
            if (!$article->getSlug()) {
                $article->setSlug($this->generateSlug($article->getTitle()));
            }
            
            $article->calculateReadingTime();
            $article->setUpdatedAt(new \DateTime());
            
            // Якщо статус опубліковано і дати публікації немає
            if ($article->getStatus() === Article::STATUS_PUBLISHED && !$article->getPublishedAt()) {
                $article->setPublishedAt(new \DateTime());
            }
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Статтю оновлено');
            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/article/edit.html.twig', [
            'form' => $form->createView(),
            'article' => $article,
            'allTags' => $tagRepository->findAll(),
        ]);
    }

    /**
     * Зміна статусу статті (AJAX)
     */
    #[Route('/{id}/change-status/{status}', name: 'admin_article_change_status', methods: ['POST'])]
    public function changeStatus(Request $request, Article $article, string $status): JsonResponse
    {
        // CSRF перевірка
        $token = $request->request->get('_token') 
            ?? $request->headers->get('X-CSRF-TOKEN');
        
        if (!$this->isCsrfTokenValid('change-status' . $article->getId(), $token)) {
            return $this->json(['success' => false, 'message' => 'Невірний CSRF токен'], 400);
        }
        
        $allowedStatuses = [
            Article::STATUS_PUBLISHED,
            Article::STATUS_DRAFT,
            Article::STATUS_PENDING,
            Article::STATUS_ARCHIVED,
            Article::STATUS_APPROVED,
            Article::STATUS_REJECTED
        ];
        
        if (!in_array($status, $allowedStatuses)) {
            return $this->json(['success' => false, 'message' => 'Невірний статус'], 400);
        }
        
        $oldStatus = $article->getStatus();
        $article->setStatus($status);
        
        // Якщо статус змінюється на опубліковано
        if ($status === Article::STATUS_PUBLISHED && !$article->getPublishedAt()) {
            $article->setPublishedAt(new \DateTime());
        }
        
        // Якщо статус змінюється на схвалено або відхилено
        if (in_array($status, [Article::STATUS_APPROVED, Article::STATUS_REJECTED]) && !$article->getModerator()) {
            $article->setModerator($this->getUser());
        }
        
        $article->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => sprintf('Статус змінено на "%s"', $this->getStatusLabel($status)),
            'newStatus' => $status,
            'statusLabel' => $this->getStatusLabel($status),
            'statusClass' => $this->getStatusClass($status),
        ]);
    }

    /**
     * Масове оновлення статусів (AJAX)
     */
    #[Route('/bulk-change-status', name: 'admin_articles_bulk_change_status', methods: ['POST'])]
    public function bulkChangeStatus(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $articleIds = $data['ids'] ?? [];
        $status = $data['status'] ?? null;
        $token = $request->headers->get('X-CSRF-TOKEN') ?? $data['_token'] ?? null;
        
        // Перевірка CSRF
        if (!$this->isCsrfTokenValid('bulk-article-action', $token)) {
            return $this->json(['success' => false, 'message' => 'Невірний CSRF токен'], 400);
        }
        
        if (empty($articleIds)) {
            return $this->json(['success' => false, 'message' => 'Не вибрано жодної статті'], 400);
        }
        
        if (!$status || !in_array($status, [
            Article::STATUS_DRAFT,
            Article::STATUS_PENDING,
            Article::STATUS_APPROVED,
            Article::STATUS_PUBLISHED,
            Article::STATUS_REJECTED,
            Article::STATUS_ARCHIVED
        ])) {
            return $this->json(['success' => false, 'message' => 'Невірний статус'], 400);
        }
        
        $articles = $this->entityManager->getRepository(Article::class)->findBy(['id' => $articleIds]);
        $updatedCount = 0;
        $errors = [];
        
        foreach ($articles as $article) {
            try {
                $oldStatus = $article->getStatus();
                $article->setStatus($status);
                
                if ($status === Article::STATUS_PUBLISHED && $oldStatus !== Article::STATUS_PUBLISHED) {
                    $article->setPublishedAt(new \DateTime());
                }
                
                if (in_array($status, [Article::STATUS_APPROVED, Article::STATUS_REJECTED]) && !$article->getModerator()) {
                    $article->setModerator($this->getUser());
                }
                
                $article->setUpdatedAt(new \DateTime());
                $updatedCount++;
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $article->getId(),
                    'title' => $article->getTitle(),
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => "Оновлено {$updatedCount} статей",
            'updatedCount' => $updatedCount,
            'errors' => $errors,
        ]);
    }

    /**
     * Видалення статті (AJAX)
     */
    #[Route('/{id}/delete', name: 'admin_article_delete', methods: ['POST', 'DELETE'])]
    public function delete(Request $request, Article $article): JsonResponse
    {
        // Отримуємо токен з POST або заголовків
        $token = $request->request->get('_token') 
            ?? $request->headers->get('X-CSRF-TOKEN');
        
        if (!$this->isCsrfTokenValid('delete' . $article->getId(), $token)) {
            return $this->json(['success' => false, 'message' => 'Невірний CSRF токен'], 400);
        }
        
        try {
            // Видаляємо зображення якщо є
            if ($article->getCoverImage()) {
                $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/articles/' . $article->getCoverImage();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            $this->entityManager->remove($article);
            $this->entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Статтю видалено'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Помилка видалення: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Масове видалення статей (AJAX)
     */
    #[Route('/bulk-delete', name: 'admin_articles_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $articleIds = $data['ids'] ?? [];
        $token = $request->headers->get('X-CSRF-TOKEN') ?? $data['_token'] ?? null;
        
        // Перевірка CSRF
        if (!$this->isCsrfTokenValid('bulk-article-action', $token)) {
            return $this->json(['success' => false, 'message' => 'Невірний CSRF токен'], 400);
        }
        
        if (empty($articleIds)) {
            return $this->json(['success' => false, 'message' => 'Не вибрано жодної статті'], 400);
        }
        
        $articles = $this->entityManager->getRepository(Article::class)->findBy(['id' => $articleIds]);
        $deletedCount = 0;
        $errors = [];
        
        foreach ($articles as $article) {
            try {
                // Видаляємо зображення
                if ($article->getCoverImage()) {
                    $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/articles/' . $article->getCoverImage();
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
                
                $this->entityManager->remove($article);
                $deletedCount++;
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $article->getId(),
                    'title' => $article->getTitle(),
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => "Видалено {$deletedCount} статей",
            'deletedCount' => $deletedCount,
            'errors' => $errors,
        ]);
    }

    /**
     * Експорт статей у CSV
     */
    #[Route('/export', name: 'admin_articles_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $status = $request->query->get('status');
        
        $queryBuilder = $this->entityManager->getRepository(Article::class)->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->leftJoin('a.author', 'u')
            ->addSelect('c', 'u');
        
        if ($status && in_array($status, [
            Article::STATUS_DRAFT,
            Article::STATUS_PENDING,
            Article::STATUS_APPROVED,
            Article::STATUS_PUBLISHED,
            Article::STATUS_REJECTED,
            Article::STATUS_ARCHIVED
        ])) {
            $queryBuilder->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }
        
        $articles = $queryBuilder->getQuery()->getResult();
        
        // Формуємо CSV
        $filename = sprintf('articles_%s.csv', date('Y-m-d_H-i-s'));
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        
        $handle = fopen('php://temp', 'w+');
        
        // Заголовки
        fputcsv($handle, [
            'ID',
            'Заголовок',
            'Категорія',
            'Автор',
            'Статус',
            'Переглядів',
            'Лайків',
            'Коментарів',
            'Створено',
            'Опубліковано'
        ]);
        
        // Дані
        foreach ($articles as $article) {
            fputcsv($handle, [
                $article->getId(),
                $article->getTitle(),
                $article->getCategory()?->getName() ?: 'Без категорії',
                $article->getAuthor()?->getUsername() ?: 'Невідомий',
                $this->getStatusLabel($article->getStatus()),
                $article->getViews(),
                $article->getLikeCount(),
                $article->getCommentCount(),
                $article->getCreatedAt()?->format('Y-m-d H:i:s'),
                $article->getPublishedAt()?->format('Y-m-d H:i:s'),
            ]);
        }
        
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        
        $response->setContent($content);
        
        return $response;
    }

    /**
     * Отримання CSRF токена
     */
    #[Route('/csrf-token', name: 'admin_article_csrf_token', methods: ['GET'])]
    public function getCsrfToken(Request $request): JsonResponse
    {
        $tokenId = $request->query->get('tokenId', 'admin_article_action');
        
        return $this->json([
            'success' => true,
            'token' => $this->csrfTokenManager->getToken($tokenId)->getValue(),
            'tokenId' => $tokenId,
        ]);
    }

    // ============================================
    // ДОПОМІЖНІ МЕТОДИ
    // ============================================

    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $title), '-'));
        
        $existing = $this->entityManager->getRepository(Article::class)->findOneBy(['slug' => $slug]);
        if ($existing) {
            $slug = $slug . '-' . uniqid();
        }
        
        return $slug;
    }

    private function getStatusLabel(string $status): string
    {
        $labels = [
            Article::STATUS_PUBLISHED => 'Опубліковано',
            Article::STATUS_DRAFT => 'Чернетка',
            Article::STATUS_PENDING => 'На модерації',
            Article::STATUS_ARCHIVED => 'Архів',
            Article::STATUS_APPROVED => 'Схвалено',
            Article::STATUS_REJECTED => 'Відхилено'
        ];
        return $labels[$status] ?? $status;
    }

    private function getStatusClass(string $status): string
    {
        $classes = [
            Article::STATUS_PUBLISHED => 'bg-green-100 text-green-800',
            Article::STATUS_DRAFT => 'bg-amber-100 text-amber-800',
            Article::STATUS_PENDING => 'bg-blue-100 text-blue-800',
            Article::STATUS_APPROVED => 'bg-purple-100 text-purple-800',
            Article::STATUS_REJECTED => 'bg-red-100 text-red-800',
            Article::STATUS_ARCHIVED => 'bg-gray-100 text-gray-800'
        ];
        return $classes[$status] ?? 'bg-gray-100 text-gray-800';
    }
}