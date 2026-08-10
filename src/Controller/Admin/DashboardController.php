<?php

namespace App\Controller\Admin;

use App\Entity\Article\Article;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use App\Repository\ArticleCommentRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function index(
        ArticleRepository $articleRepository,
        CategoryRepository $categoryRepository,
        ArticleCommentRepository $commentRepository,
        UserRepository $userRepository
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Отримуємо статистику
        $totalArticles = $articleRepository->count([]);
        
        // Кількість опублікованих статей (статус 'published')
        $publishedArticles = $articleRepository->count(['status' => Article::STATUS_PUBLISHED]);
        
        // Кількість статей на модерації
        $pendingArticles = $articleRepository->count(['status' => Article::STATUS_PENDING]);
        
        // Кількість чорнеток
        $draftArticles = $articleRepository->count(['status' => Article::STATUS_DRAFT]);
        
        // Кількість архівних статей
        $archivedArticles = $articleRepository->count(['status' => Article::STATUS_ARCHIVED]);
        
        // Інша статистика
        $totalCategories = $categoryRepository->count([]);
        $totalComments = $commentRepository->count([]);
        $pendingComments = $commentRepository->count(['isApproved' => false]);
        $totalUsers = $userRepository->count([]);
        
        // Статистика коментарів
        $spamComments = $commentRepository->count(['isSpam' => true]);
        $approvedComments = $commentRepository->count(['isApproved' => true]);
        
        // Отримуємо останні статті (всі статуси)
        $recentArticles = $articleRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            10
        );
        
        // Отримуємо останні коментарі
        $recentComments = $commentRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            5
        );
        
        // Отримуємо останні статті, що потребують модерації
        $pendingReviewArticles = $articleRepository->findBy(
            ['status' => Article::STATUS_PENDING],
            ['createdAt' => 'DESC'],
            5
        );
        
        // Статистика за сьогодні
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        
        // Статті створені сьогодні
        $todayArticles = $articleRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.createdAt >= :today')
            ->andWhere('a.createdAt < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();
        
        // Статті опубліковані сьогодні
        $todayPublishedArticles = $articleRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.publishedAt >= :today')
            ->andWhere('a.publishedAt < :tomorrow')
            ->andWhere('a.status = :status')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('status', Article::STATUS_PUBLISHED)
            ->getQuery()
            ->getSingleScalarResult();
        
        // Кількість переглядів за сьогодні (якщо є лог переглядів)
        // Тут потрібна додаткова логіка, якщо ви хочете трекати перегляди по днях
        
        // Статистика по пріоритетах
        $highPriorityArticles = $articleRepository->count(['priority' => Article::PRIORITY_HIGH]);
        $mediumPriorityArticles = $articleRepository->count(['priority' => Article::PRIORITY_MEDIUM]);
        $lowPriorityArticles = $articleRepository->count(['priority' => Article::PRIORITY_LOW]);
        
        // Статті без пріоритету
        $noPriorityArticles = $articleRepository->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.priority IS NULL OR a.priority = :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleScalarResult();
        
        // Статистика по авторах
        $topAuthors = $articleRepository->createQueryBuilder('a')
            ->select('COUNT(a.id) as article_count', 'u.username', 'u.mail')
            ->join('a.author', 'u')
            ->groupBy('a.author')
            ->orderBy('article_count', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return $this->render('admin/dashboard/index.html.twig', [
            // Основна статистика
            'total_articles' => $totalArticles,
            'published_articles' => $publishedArticles,
            'pending_articles' => $pendingArticles,
            'draft_articles' => $draftArticles,
            'archived_articles' => $archivedArticles,
            'total_categories' => $totalCategories,
            'total_comments' => $totalComments,
            'pending_comments' => $pendingComments,
            'approved_comments' => $approvedComments,
            'spam_comments' => $spamComments,
            'total_users' => $totalUsers,
            
            // Останні дані
            'recent_articles' => $recentArticles,
            'recent_comments' => $recentComments,
            'pending_review_articles' => $pendingReviewArticles,
            
            // Додаткова статистика
            'today_articles' => $todayArticles,
            'today_published_articles' => $todayPublishedArticles,
            
            // Статистика пріоритетів
            'high_priority_articles' => $highPriorityArticles,
            'medium_priority_articles' => $mediumPriorityArticles,
            'low_priority_articles' => $lowPriorityArticles,
            'no_priority_articles' => $noPriorityArticles,
            
            // Топ автори
            'top_authors' => $topAuthors,
            
            // Статуси для відображення
            'article_statuses' => Article::getStatuses(),
            'article_priorities' => Article::getPriorities(),
        ]);
    }
    
    #[Route('/admin/stats', name: 'admin_stats')]
    public function stats(
        ArticleRepository $articleRepository,
        CommentRepository $commentRepository,
        UserRepository $userRepository
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Статистика за останні 7 днів
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-7 days');
        
        // Статті за останні 7 днів
        $articlesByDate = $articleRepository->createQueryBuilder('a')
            ->select('DATE(a.createdAt) as date', 'COUNT(a.id) as count')
            ->where('a.createdAt >= :startDate')
            ->andWhere('a.createdAt <= :endDate')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();
        
        // Статті за статусом (для кругової діаграми)
        $articlesByStatus = $articleRepository->createQueryBuilder('a')
            ->select('a.status', 'COUNT(a.id) as count')
            ->groupBy('a.status')
            ->getQuery()
            ->getResult();
        
        // Коментарі за останні 7 днів
        $commentsByDate = $commentRepository->createQueryBuilder('c')
            ->select('DATE(c.createdAt) as date', 'COUNT(c.id) as count')
            ->where('c.createdAt >= :startDate')
            ->andWhere('c.createdAt <= :endDate')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();
        
        // Користувачі за останні 7 днів
        $usersByDate = $userRepository->createQueryBuilder('u')
            ->select('DATE(u.createdAt) as date', 'COUNT(u.id) as count')
            ->where('u.createdAt >= :startDate')
            ->andWhere('u.createdAt <= :endDate')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();
        
        // Формуємо дані для графіків
        $chartData = [
            'articles' => $this->prepareChartData($articlesByDate, $startDate, $endDate),
            'comments' => $this->prepareChartData($commentsByDate, $startDate, $endDate),
            'users' => $this->prepareChartData($usersByDate, $startDate, $endDate),
            'statuses' => $this->prepareStatusData($articlesByStatus),
        ];
        
        return $this->render('admin/dashboard/stats.html.twig', [
            'chart_data' => $chartData,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }
    
    /**
     * Підготовка даних для графіків
     */
    private function prepareChartData(array $data, \DateTime $startDate, \DateTime $endDate): array
    {
        $result = [];
        $currentDate = clone $startDate;
        
        // Створюємо масив з усіма датами в діапазоні
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $result[$dateStr] = [
                'date' => $currentDate->format('d.m'),
                'count' => 0,
            ];
            $currentDate->modify('+1 day');
        }
        
        // Заповнюємо реальними даними
        foreach ($data as $item) {
            if (isset($item['date'])) {
                $date = \DateTime::createFromFormat('Y-m-d', $item['date'])->format('Y-m-d');
                if (isset($result[$date])) {
                    $result[$date]['count'] = (int) $item['count'];
                }
            }
        }
        
        return array_values($result);
    }
    
    /**
     * Підготовка даних за статусами
     */
    private function prepareStatusData(array $data): array
    {
        $statusLabels = [
            Article::STATUS_DRAFT => 'Чернетки',
            Article::STATUS_PENDING => 'На модерації',
            Article::STATUS_PUBLISHED => 'Опубліковано',
            Article::STATUS_ARCHIVED => 'Архів',
        ];
        
        $result = [];
        foreach ($data as $item) {
            $status = $item['status'];
            $label = $statusLabels[$status] ?? $status;
            $result[] = [
                'label' => $label,
                'count' => (int) $item['count'],
                'status' => $status,
            ];
        }
        
        return $result;
    }
}