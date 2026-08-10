<?php

namespace App\Controller\Admin;

use App\Entity\Blog\BlogComment;
use App\Repository\BlogCommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/blog/comments')]
class CommentModerationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BlogCommentRepository $commentRepository
    ) {}

    #[Route('/', name: 'admin_blog_comments_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $status = $request->query->get('status', 'pending');
        $search = $request->query->get('search', '');

        $queryBuilder = $this->commentRepository->createQueryBuilder('c')
            ->leftJoin('c.blogPost', 'bp')
            ->leftJoin('c.user', 'u')
            ->addSelect('bp', 'u')
            ->orderBy('c.createdAt', 'DESC');

        // Фільтрація за статусом
        switch ($status) {
            case 'pending':
                $queryBuilder->andWhere('c.isApproved = :approved AND c.isSpam = :spam')
                    ->setParameter('approved', false)
                    ->setParameter('spam', false);
                break;
            case 'approved':
                $queryBuilder->andWhere('c.isApproved = :approved')
                    ->setParameter('approved', true);
                break;
            case 'spam':
                $queryBuilder->andWhere('c.isSpam = :spam')
                    ->setParameter('spam', true);
                break;
            case 'rejected':
                $queryBuilder->andWhere('c.isApproved = :approved')
                    ->setParameter('approved', false);
                break;
        }

        // Пошук за текстом
        if (!empty($search)) {
            $queryBuilder->andWhere('c.content LIKE :search OR c.authorName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            20
        );

        // Статистика
        $stats = [
            'pending' => $this->commentRepository->count(['isApproved' => false, 'isSpam' => false]),
            'approved' => $this->commentRepository->count(['isApproved' => true]),
            'spam' => $this->commentRepository->count(['isSpam' => true]),
            'rejected' => $this->commentRepository->count(['isApproved' => false, 'isSpam' => false]),
            'total' => $this->commentRepository->count([]),
        ];

        return $this->render('admin/blog/comment.html.twig', [
            'pagination' => $pagination,
            'stats' => $stats,
            'currentStatus' => $status,
            'search' => $search,
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_blog_comment_approve', methods: ['POST'])]
    public function approve(Request $request, BlogComment $comment): Response
    {
        if (!$this->isCsrfTokenValid('approve' . $comment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недійсний CSRF токен');
            return $this->redirectToRoute('admin_blog_comments_index');
        }

        $comment->setIsApproved(true);
        $comment->setIsSpam(false);
        $this->entityManager->flush();

        $this->addFlash('success', 'Коментар схвалено');
        return $this->redirectToRoute('admin_blog_comments_index');
    }

    #[Route('/{id}/reject', name: 'admin_blog_comment_reject', methods: ['POST'])]
    public function reject(Request $request, BlogComment $comment): Response
    {
        if (!$this->isCsrfTokenValid('reject' . $comment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недійсний CSRF токен');
            return $this->redirectToRoute('admin_blog_comments_index');
        }

        $comment->setIsApproved(false);
        $comment->setIsSpam(false);
        $this->entityManager->flush();

        $this->addFlash('success', 'Коментар відхилено');
        return $this->redirectToRoute('admin_blog_comments_index');
    }

    #[Route('/{id}/mark-spam', name: 'admin_blog_comment_mark_spam', methods: ['POST'])]
    public function markSpam(Request $request, BlogComment $comment): Response
    {
        if (!$this->isCsrfTokenValid('spam' . $comment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недійсний CSRF токен');
            return $this->redirectToRoute('admin_blog_comments_index');
        }

        $comment->setIsSpam(true);
        $comment->setIsApproved(false);
        $this->entityManager->flush();

        $this->addFlash('success', 'Коментар позначено як спам');
        return $this->redirectToRoute('admin_blog_comments_index');
    }

    #[Route('/{id}/delete', name: 'admin_blog_comment_delete', methods: ['POST'])]
    public function delete(Request $request, BlogComment $comment): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $comment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Недійсний CSRF токен');
            return $this->redirectToRoute('admin_blog_comments_index');
        }

        $this->entityManager->remove($comment);
        $this->entityManager->flush();

        $this->addFlash('success', 'Коментар видалено');
        return $this->redirectToRoute('admin_blog_comments_index');
    }

    #[Route('/bulk-action', name: 'admin_blog_comment_bulk', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('bulk-action', $request->request->get('_token'))) {
            $this->addFlash('error', 'Недійсний CSRF токен');
            return $this->redirectToRoute('admin_blog_comments_index');
        }

        $action = $request->request->get('action');
        $commentIds = $request->request->all('comments') ?? [];

        if (empty($commentIds)) {
            $this->addFlash('warning', 'Не вибрано жодного коментаря');
            return $this->redirectToRoute('admin_blog_comments_index');
        }

        $comments = $this->commentRepository->findBy(['id' => $commentIds]);
        $count = 0;

        foreach ($comments as $comment) {
            switch ($action) {
                case 'approve':
                    $comment->setIsApproved(true);
                    $comment->setIsSpam(false);
                    $count++;
                    break;
                case 'reject':
                    $comment->setIsApproved(false);
                    $comment->setIsSpam(false);
                    $count++;
                    break;
                case 'spam':
                    $comment->setIsSpam(true);
                    $comment->setIsApproved(false);
                    $count++;
                    break;
                case 'delete':
                    $this->entityManager->remove($comment);
                    $count++;
                    break;
            }
        }

        $this->entityManager->flush();

        $actionNames = [
            'approve' => 'схвалено',
            'reject' => 'відхилено',
            'spam' => 'позначено спамом',
            'delete' => 'видалено'
        ];

        $this->addFlash('success', "{$count} коментарів {$actionNames[$action]}");

        return $this->redirectToRoute('admin_blog_comments_index');
    }

    #[Route('/stats/json', name: 'admin_blog_comment_stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        $stats = [
            'pending' => $this->commentRepository->count(['isApproved' => false, 'isSpam' => false]),
            'approved' => $this->commentRepository->count(['isApproved' => true]),
            'spam' => $this->commentRepository->count(['isSpam' => true]),
        ];

        return $this->json($stats);
    }

    #[Route('/{id}/reply', name: 'admin_blog_comment_reply', methods: ['POST'])]
    public function reply(Request $request, BlogComment $parent): Response
    {
        if (!$this->isCsrfTokenValid('reply' . $parent->getId(), $request->request->get('_token'))) {
            return $this->json(['error' => 'Invalid CSRF token'], 400);
        }

        $content = $request->request->get('content');
        if (empty($content)) {
            return $this->json(['error' => 'Content cannot be empty'], 400);
        }

        $reply = new BlogComment();
        $reply->setContent($content);
        $reply->setBlogPost($parent->getBlogPost());
        $reply->setParent($parent);
        $reply->setIsApproved(true); // Відповіді адміна автоматично схвалені
        $reply->setUser($this->getUser());
        $reply->setAuthorName($this->getUser()->getUsername());
        $reply->setAuthorIp($request->getClientIp());
        $reply->setAuthorUserAgent($request->headers->get('User-Agent'));

        $this->entityManager->persist($reply);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Відповідь додано',
            'reply' => [
                'id' => $reply->getId(),
                'content' => $reply->getContent(),
                'author' => $reply->getDisplayName(),
                'createdAt' => $reply->getCreatedAt()->format('d.m.Y H:i'),
            ]
        ]);
    }
}