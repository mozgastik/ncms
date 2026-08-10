<?php

namespace App\Controller\Admin;

use App\Entity\Blog\BlogPost;
use App\Entity\Blog\BlogModerationLog;
use App\Entity\Admin\Tag;
use App\Form\BlogModerationType;
use App\Repository\BlogPostRepository;
use App\Repository\BlogCategoryRepository;
use App\Repository\TagRepository;
use App\Service\ReadingTimeCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/admin/blog')]
#[IsGranted('ROLE_ADMIN')]
class BlogController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BlogPostRepository $blogPostRepository,
        private BlogCategoryRepository $blogCategoryRepository,
        private TagRepository $tagRepository,
        private ReadingTimeCalculator $readingTimeCalculator
    )  {}

    /**
     * Панель управління блогами
     */
    #[Route('/', name: 'admin_blog_index')]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $queryBuilder = $this->blogPostRepository->createQueryBuilder('bp')
            ->orderBy('bp.createdAt', 'DESC');

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            20
        );

        // Статистика
        $stats = [
            'total' => $this->blogPostRepository->count([]),
            'published' => $this->blogPostRepository->count(['status' => BlogPost::STATUS_PUBLISHED]),
            'pending' => $this->blogPostRepository->count(['status' => BlogPost::STATUS_PENDING]),
            'drafts' => $this->blogPostRepository->count(['status' => BlogPost::STATUS_DRAFT]),
            'rejected' => $this->blogPostRepository->count(['status' => BlogPost::STATUS_REJECTED]),
        ];

        return $this->render('admin/blog/index.html.twig', [
            'pagination' => $pagination,
            'stats' => $stats,
        ]);
    }

    /**
     * Блоги на модерації
     */
    #[Route('/moderation', name: 'admin_blog_moderation')]
    public function moderation(Request $request, PaginatorInterface $paginator): Response
    {
        $queryBuilder = $this->blogPostRepository->createQueryBuilder('bp')
            ->where('bp.status = :status')
            ->setParameter('status', BlogPost::STATUS_PENDING)
            ->orderBy('bp.createdAt', 'ASC');

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            15
        );

        return $this->render('admin/blog/moderation.html.twig', [
            'pagination' => $pagination,
        ]);
    }

     /**
     * Перегляд блогу для модерації
     */
    #[Route('/{id}/review', name: 'admin_blog_review', methods: ['GET', 'POST'])]
    public function review(BlogPost $blogPost, Request $request): Response
    {
        $form = $this->createForm(BlogModerationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $action = $form->get('action')->getData();
            $notes = $form->get('notes')->getData();

            if ($action === 'approve') {
                return $this->approvePost($blogPost, $notes);
            } elseif ($action === 'reject') {
                return $this->rejectPost($blogPost, $notes);
            } elseif ($action === 'revise') {
                return $this->revisePost($blogPost, $notes);
            }
        }

        return $this->render('admin/blog/review.html.twig', [
            'post' => $blogPost,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Відправити на доопрацювання
     */
    #[Route('/{id}/revise', name: 'admin_blog_revise', methods: ['POST'])]
    public function revise(BlogPost $blogPost, Request $request): Response
    {
        $notes = $request->request->get('notes', '');

        return $this->revisePost($blogPost, $notes);
    }


    /**
     * Схвалити блог
     */
    #[Route('/{id}/approve', name: 'admin_blog_approve', methods: ['POST'])]
    public function approve(BlogPost $blogPost, Request $request): Response
    {
        $notes = $request->request->get('notes', '');

        return $this->approvePost($blogPost, $notes);
    }

    /**
     * Відхилити блог
     */
    #[Route('/{id}/reject', name: 'admin_blog_reject', methods: ['POST'])]
    public function reject(BlogPost $blogPost, Request $request): Response
    {
        $notes = $request->request->get('notes', '');

        return $this->rejectPost($blogPost, $notes);
    }
    /**
     * Зробити блог рекомендованим
     */
    #[Route('/{id}/feature', name: 'admin_blog_feature', methods: ['POST'])]
    public function feature(BlogPost $blogPost): JsonResponse
    {
        $blogPost->setIsFeatured(!$blogPost->isFeatured());
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'isFeatured' => $blogPost->isFeatured(),
            'message' => $blogPost->isFeatured() 
                ? 'Блог додано до рекомендованих' 
                : 'Блог видалено з рекомендованих',
        ]);
    }

    /**
     * Позначити як новину
     */
    #[Route('/{id}/breaking', name: 'admin_blog_breaking', methods: ['POST'])]
    public function breaking(BlogPost $blogPost): JsonResponse
    {
        $blogPost->setIsBreaking(!$blogPost->isBreaking());
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'isBreaking' => $blogPost->isBreaking(),
            'message' => $blogPost->isBreaking() 
                ? 'Блог позначено як новина' 
                : 'Знято позначку новини',
        ]);
    }

    /**
     * Опублікувати/приховати блог
     */
    #[Route('/{id}/toggle-publish', name: 'admin_blog_toggle_publish', methods: ['POST'])]
    public function togglePublish(BlogPost $blogPost): JsonResponse
    {
        if ($blogPost->getStatus() === BlogPost::STATUS_PUBLISHED) {
            $blogPost->setStatus(BlogPost::STATUS_DRAFT);
            $blogPost->setPublishedAt(null);
            $message = 'Блог приховано';
        } else {
            $blogPost->setStatus(BlogPost::STATUS_PUBLISHED);
            $blogPost->setPublishedAt(new \DateTimeImmutable());
            $message = 'Блог опубліковано';
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'status' => $blogPost->getStatus(),
            'message' => $message,
        ]);
    }

    /**
     * Видалити блог
     */
    #[Route('/{id}/delete', name: 'admin_blog_delete', methods: ['POST'])]
    public function delete(BlogPost $blogPost, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete' . $blogPost->getId(), $request->request->get('_token'))) {
            $title = $blogPost->getTitle();
            
            $this->entityManager->remove($blogPost);
            $this->entityManager->flush();

            $this->addFlash('success', "Блог \"{$title}\" успішно видалено.");
        }

        return $this->redirectToRoute('admin_blog_index');
    }

    /**
     * Статистика блогів
     */
    #[Route('/stats', name: 'admin_blog_stats')]
    public function stats(): Response
    {
        // Статистика за останні 30 днів
        $monthAgo = new \DateTimeImmutable('-30 days');
        
        $monthStats = $this->blogPostRepository->createQueryBuilder('bp')
            ->select([
                'COUNT(bp.id) as total',
                'SUM(CASE WHEN bp.status = :published THEN 1 ELSE 0 END) as published',
                'AVG(bp.viewCount) as avg_views',
                'SUM(bp.viewCount) as total_views'
            ])
            ->where('bp.createdAt >= :monthAgo')
            ->setParameter('published', BlogPost::STATUS_PUBLISHED)
            ->setParameter('monthAgo', $monthAgo)
            ->getQuery()
            ->getSingleResult();

        // Топ автори
        $popularAuthors = $this->blogPostRepository->createQueryBuilder('bp')
            ->select('u.id', 'u.username', 'u.mail', 'COUNT(bp.id) as post_count', 'SUM(bp.viewCount) as total_views')
            ->join('bp.author', 'u')
            ->where('bp.status = :status')
            ->setParameter('status', BlogPost::STATUS_PUBLISHED)
            ->groupBy('u.id')
            ->orderBy('post_count', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Топ блоги за переглядами
        $topBlogs = $this->blogPostRepository->createQueryBuilder('bp')
            ->select('bp', 'u', 'c')
            ->join('bp.author', 'u')
            ->leftJoin('bp.category', 'c')
            ->where('bp.status = :status')
            ->setParameter('status', BlogPost::STATUS_PUBLISHED)
            ->orderBy('bp.viewCount', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Дані для графіка активності (останні 30 днів)
        $chartData = $this->getChartData();

        // Дані для розподілу за категоріями
        $categoriesData = $this->getCategoriesData();

        // Дані для розподілу за статусами
        $statusData = $this->getStatusData();

        return $this->render('admin/blog/stats.html.twig', [
            'monthStats' => [
                'total' => (int) ($monthStats['total'] ?? 0),
                'published' => (int) ($monthStats['published'] ?? 0),
                'avg_views' => (float) ($monthStats['avg_views'] ?? 0),
                'total_views' => (int) ($monthStats['total_views'] ?? 0),
            ],
            'popularAuthors' => $popularAuthors,
            'topBlogs' => $topBlogs,
            'chartData' => $chartData,
            'categoriesData' => $categoriesData,
            'statusData' => $statusData,
        ]);
    }

    /**
     * Управління тегами
     */
    public function tags(Request $request, PaginatorInterface $paginator): Response
  {
    $queryBuilder = $this->tagRepository->createQueryBuilder('t')
        ->orderBy('t.totalUsageCount', 'DESC')
        ->addOrderBy('t.name', 'ASC');

    $pagination = $paginator->paginate(
        $queryBuilder,
        $request->query->getInt('page', 1),
        50
    );

    return $this->render('admin/blog/tags.html.twig', [
        'pagination' => $pagination,
    ]);
}

    /**
     * Історія модерації
     */
    #[Route('/moderation-logs', name: 'admin_blog_moderation_logs')]
    public function moderationLogs(Request $request, PaginatorInterface $paginator): Response
    {
        $queryBuilder = $this->entityManager->getRepository(BlogModerationLog::class)
            ->createQueryBuilder('log')
            ->orderBy('log.createdAt', 'DESC');

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('admin/blog/moderation_logs.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    /**
     * Масові дії
     */
    #[Route('/bulk-actions', name: 'admin_blog_bulk_actions', methods: ['POST'])]
    public function bulkActions(Request $request): JsonResponse
    {
        $ids = $request->request->get('ids', []);
        $action = $request->request->get('action');

        if (empty($ids) || empty($action)) {
            return $this->json(['error' => 'Необхідно вибрати блоги та дію'], 400);
        }

        $posts = $this->blogPostRepository->findBy(['id' => $ids]);
        
        $processed = 0;
        $errors = [];

        foreach ($posts as $post) {
            try {
                switch ($action) {
                    case 'approve':
                        $post->setStatus(BlogPost::STATUS_PUBLISHED);
                        $post->setPublishedAt(new \DateTimeImmutable());
                        break;
                    case 'reject':
                        $post->setStatus(BlogPost::STATUS_REJECTED);
                        break;
                    case 'delete':
                        $this->entityManager->remove($post);
                        break;
                    case 'feature':
                        $post->setIsFeatured(true);
                        break;
                    case 'unfeature':
                        $post->setIsFeatured(false);
                        break;
                }
                $processed++;
            } catch (\Exception $e) {
                $errors[] = "Помилка для блогу #{$post->getId()}: " . $e->getMessage();
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'processed' => $processed,
            'errors' => $errors,
            'message' => "Оброблено {$processed} блогів",
        ]);
    }

    /**
     * Експорт блогів
     */
    #[Route('/export', name: 'admin_blog_export')]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'csv');
        $status = $request->query->get('status');

        $criteria = [];
        if ($status) {
            $criteria['status'] = $status;
        }

        $posts = $this->blogPostRepository->findBy($criteria, ['createdAt' => 'DESC']);

        $data = [];
        foreach ($posts as $post) {
            $data[] = [
                'ID' => $post->getId(),
                'Заголовок' => $post->getTitle(),
                'Автор' => $post->getAuthor()->getUsername(),
                'Статус' => $this->getStatusLabel($post->getStatus()),
                'Перегляди' => $post->getViewCount(),
                'Лайки' => $post->getLikeCount(),
                'Дизлайки' => $post->getDislikeCount(),
                'Коментарі' => $post->getComments()->count(),
                'Створено' => $post->getCreatedAt()->format('Y-m-d H:i:s'),
                'Опубліковано' => $post->getPublishedAt() ? $post->getPublishedAt()->format('Y-m-d H:i:s') : '',
            ];
        }

        if ($format === 'json') {
            return $this->json($data);
        }

        // CSV експорт
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="blogs_export_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, array_keys($data[0] ?? []));
        
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);

        return $response;
    }

    /**
     * Допоміжний метод для отримання даних графіка
     */
   /**
 * Допоміжний метод для отримання даних графіка
 */
private function getChartData(int $days = 30): array
{
    $endDate = new \DateTimeImmutable();
    $startDate = $endDate->modify("-{$days} days");
    
    $labels = [];
    $publications = [];
    
    $currentDate = clone $startDate;
    
    // Отримуємо всі блоги за останні 30 днів
    $allPosts = $this->blogPostRepository->createQueryBuilder('bp')
        ->select('bp.createdAt')
        ->where('bp.createdAt >= :startDate')
        ->setParameter('startDate', $startDate)
        ->getQuery()
        ->getResult();
    
    // Готуємо масив для підрахунку
    $countByDate = [];
    $currentDate = clone $startDate;
    while ($currentDate <= $endDate) {
        $dateKey = $currentDate->format('Y-m-d');
        $labels[] = $currentDate->format('d.m');
        $countByDate[$dateKey] = 0;
        $currentDate = $currentDate->modify('+1 day');
    }
    
    // Рахуємо блоги по датах
    foreach ($allPosts as $post) {
        $dateKey = $post['createdAt']->format('Y-m-d');
        if (isset($countByDate[$dateKey])) {
            $countByDate[$dateKey]++;
        }
    }
    
    // Перетворюємо в масив для графіка
    $publications = array_values($countByDate);
    
    return [
        'labels' => $labels,
        'publications' => $publications
    ];
}
    /**
 * Допоміжний метод для отримання даних категорій
 */
private function getCategoriesData(): array
{
    // Варіант 1: Якщо асоціація називається по-іншому
    // Перевірте вашу сутність BlogCategory, щоб дізнатися правильну назву
    // Наприклад: 'posts', 'blogPost', 'articles' тощо
    
    // Варіант 2: Зробити запит через BlogPostRepository
    $results = $this->blogPostRepository->createQueryBuilder('bp')
        ->select([
            'c.name',
            'COUNT(bp.id) as post_count'
        ])
        ->leftJoin('bp.category', 'c')
        ->where('bp.status = :status')
        ->setParameter('status', BlogPost::STATUS_PUBLISHED)
        ->groupBy('c.id')
        ->orderBy('post_count', 'DESC')
        ->getQuery()
        ->getResult();
    
    $labels = [];
    $values = [];
    
    foreach ($results as $result) {
        $labels[] = $result['name'] ?? 'Без категорії';
        $values[] = (int) $result['post_count'];
    }
    
    return [
        'labels' => $labels,
        'values' => $values
    ];
}
    /**
     * Допоміжний метод для отримання даних статусів
     */
    private function getStatusData(): array
    {
        $draftCount = $this->blogPostRepository->count(['status' => BlogPost::STATUS_DRAFT]);
        $pendingCount = $this->blogPostRepository->count(['status' => BlogPost::STATUS_PENDING]);
        $publishedCount = $this->blogPostRepository->count(['status' => BlogPost::STATUS_PUBLISHED]);
        $rejectedCount = $this->blogPostRepository->count(['status' => BlogPost::STATUS_REJECTED]);
        
        return [$publishedCount, $draftCount, $pendingCount, $rejectedCount];
    }

    /**
     * Приватні методи для модерації
     */
    private function approvePost(BlogPost $blogPost, string $notes = ''): Response
    {
        $oldStatus = $blogPost->getStatus();
        $blogPost->setStatus(BlogPost::STATUS_PUBLISHED);
        $blogPost->setPublishedAt(new \DateTimeImmutable());
        
        if (!empty($notes)) {
            $blogPost->setModeratorNotes($notes);
        }

        $this->logModeration($blogPost, 'approved', $notes);
        $this->entityManager->flush();

        $this->addFlash('success', 'Блог успішно схвалено та опубліковано.');

        return $this->redirectToRoute('admin_blog_moderation');
    }

    private function rejectPost(BlogPost $blogPost, string $notes = ''): Response
    {
        if (empty($notes)) {
            $this->addFlash('error', 'Необхідно вказати причину відхилення.');
            return $this->redirectToRoute('admin_blog_review', ['id' => $blogPost->getId()]);
        }

        $blogPost->setStatus(BlogPost::STATUS_REJECTED);
        $blogPost->setModeratorNotes($notes);

        $this->logModeration($blogPost, 'rejected', $notes);
        $this->entityManager->flush();

        $this->addFlash('success', 'Блог відхилено. Автор отримає повідомлення з причиною.');

        return $this->redirectToRoute('admin_blog_moderation');
    }

    private function revisePost(BlogPost $blogPost, string $notes = ''): Response
    {
        if (empty($notes)) {
            $this->addFlash('error', 'Будь ласка, вкажіть що саме потрібно доопрацювати.');
            return $this->redirectToRoute('admin_blog_review', ['id' => $blogPost->getId()]);
        }

        $blogPost->setStatus(BlogPost::STATUS_DRAFT); // або можна створити окремий статус для доопрацювання
        $blogPost->setModeratorNotes($notes);
        $blogPost->setNeedsRevision(true); // якщо є такий флаг

        $this->logModeration($blogPost, 'revised', $notes);
        $this->entityManager->flush();

        $this->addFlash('warning', 'Блог відправлено на доопрацювання. Автор отримав коментарі.');

        return $this->redirectToRoute('admin_blog_moderation');
    }

    private function logModeration(BlogPost $blogPost, string $action, string $notes = ''): void
    {
        $log = new BlogModerationLog();
        $log->setBlogPost($blogPost);
        $log->setModerator($this->getUser());
        $log->setAction($action);
        $log->setNotes($notes);
        $log->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($log);
    }

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