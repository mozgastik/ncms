<?php

namespace App\Controller\Api;

use App\Entity\Article\Article;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/user')]
class UserArticleApiController extends AbstractController
{
    private ArticleRepository $articleRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        ArticleRepository $articleRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->articleRepository = $articleRepository;
        $this->entityManager = $entityManager;
    }

    #[Route('/articles', name: 'api_user_articles', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getArticles(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $status = $request->query->get('status', 'all');
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        
        // Отримуємо всі статті користувача
        $queryBuilder = $this->articleRepository->createQueryBuilder('a')
            ->where('a.author = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC');
        
        // Фільтр за статусом
        if ($status !== 'all' && in_array($status, [
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
        
        // Отримуємо загальну кількість
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
        
        // Форматуємо дані
        $data = array_map(function(Article $article) {
            return [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'slug' => $article->getSlug(),
                'excerpt' => $article->getExcerpt(),
                'coverImage' => $article->getCoverImage(),
                'status' => $article->getStatus(),
                'views' => $article->getViews(),
                'likeCount' => $article->getLikeCount(),
                'commentCount' => $article->getCommentCount(),
                'isBreaking' => $article->isBreaking(),
                'isFeatured' => $article->isFeatured(),
                'isPinned' => $article->isPinned(),
                'rejectionReason' => $article->getRejectionReason(),
                'category' => $article->getCategory() ? [
                    'id' => $article->getCategory()->getId(),
                    'name' => $article->getCategory()->getName(),
                    'slug' => $article->getCategory()->getSlug(),
                ] : null,
                'author' => [
                    'id' => $article->getAuthor()->getId(),
                    'username' => $article->getAuthor()->getUsername(),
                ],
                'createdAt' => $article->getCreatedAt() ? $article->getCreatedAt()->format('c') : null,
                'publishedAt' => $article->getPublishedAt() ? $article->getPublishedAt()->format('c') : null,
            ];
        }, $articles);
        
        // Статистика
        $stats = $this->getUserStats($user);
        
        return $this->json([
            'success' => true,
            'articles' => $data,
            'stats' => $stats,
            'pagination' => [
                'currentPage' => $page,
                'itemsPerPage' => $limit,
                'totalItems' => (int) $totalItems,
                'totalPages' => ceil($totalItems / $limit),
            ]
        ]);
    }

    #[Route('/article/{id}/submit', name: 'api_user_article_submit', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function submitArticle(Article $article): JsonResponse
    {
        $user = $this->getUser();
        
        // Перевірка прав
        if ($article->getAuthor()->getId() !== $user->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'У вас немає прав на цю дію'
            ], 403);
        }
        
        // Перевірка статусу
        if ($article->getStatus() !== Article::STATUS_DRAFT) {
            return $this->json([
                'success' => false,
                'message' => 'Стаття не може бути відправлена на модерацію'
            ], 400);
        }
        
        // Перевірка контенту
        if (!$article->canBeSubmitted()) {
            return $this->json([
                'success' => false,
                'message' => 'Стаття не готова до відправки. Заповніть всі обов\'язкові поля.'
            ], 400);
        }
        
        $article->sendToModeration();
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Статтю відправлено на модерацію',
            'article' => [
                'id' => $article->getId(),
                'status' => $article->getStatus(),
            ]
        ]);
    }

    #[Route('/article/{id}/delete', name: 'api_user_article_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deleteArticle(Article $article): JsonResponse
    {
        $user = $this->getUser();
        
        // Перевірка прав
        if ($article->getAuthor()->getId() !== $user->getId()) {
            return $this->json([
                'success' => false,
                'message' => 'У вас немає прав на цю дію'
            ], 403);
        }
        
        // Перевірка статусу (не можна видалити опубліковану статтю)
        if ($article->getStatus() === Article::STATUS_PUBLISHED) {
            return $this->json([
                'success' => false,
                'message' => 'Опубліковану статтю не можна видалити'
            ], 400);
        }
        
        $this->entityManager->remove($article);
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Статтю видалено'
        ]);
    }

    private function getUserStats($user): array
    {
        $statuses = [
            Article::STATUS_DRAFT,
            Article::STATUS_PENDING,
            Article::STATUS_APPROVED,
            Article::STATUS_PUBLISHED,
            Article::STATUS_REJECTED,
            Article::STATUS_ARCHIVED
        ];
        
        $stats = [
            'total' => 0,
            'draft' => 0,
            'pending' => 0,
            'approved' => 0,
            'published' => 0,
            'rejected' => 0,
            'archived' => 0,
        ];
        
        foreach ($statuses as $status) {
            $count = $this->articleRepository->count([
                'author' => $user,
                'status' => $status
            ]);
            $stats[$status] = $count;
            $stats['total'] += $count;
        }
        
        return $stats;
    }
}