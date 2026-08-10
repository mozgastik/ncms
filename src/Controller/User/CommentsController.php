<?php

namespace App\Controller\User;

use App\Entity\Article\Comment;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user/comments')]
#[IsGranted('ROLE_USER')]
class CommentsController extends AbstractController
{
    #[Route('', name: 'user_comments', methods: ['GET'])]
    public function index(
        CommentRepository $commentRepository,
        PaginatorInterface $paginator,
        Request $request
    ): Response
    {
        $user = $this->getUser();
        $status = $request->query->get('status', 'all');
        
        // Створюємо базовий запит
        $queryBuilder = $commentRepository->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->leftJoin('c.article', 'a')
            ->addSelect('a')
            ->orderBy('c.createdAt', 'DESC');
        
        // Застосовуємо фільтр за статусом
        if ($status === 'approved') {
            $queryBuilder->andWhere('c.isApproved = :approved')
                ->setParameter('approved', true);
        } elseif ($status === 'pending') {
            $queryBuilder->andWhere('c.isApproved = :approved')
                ->setParameter('approved', false);
        }
        
        $query = $queryBuilder->getQuery();
        
        // Пагінація
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10
        );
        
        // Отримуємо всі коментарі для статистики
        $allComments = $commentRepository->findBy(['user' => $user]);
        
        return $this->render('user/comments.html.twig', [
            'comments' => $allComments,
            'pagination' => $pagination,
        ]);
    }
    
    #[Route('/{id}/edit', name: 'user_comment_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Comment $comment,
        EntityManagerInterface $entityManager
    ): Response
    {
        // Перевіряємо, чи коментар належить користувачу
        if ($comment->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Цей коментар не належить вам');
        }
        
        // Перевіряємо, чи коментар ще не схвалений
        if ($comment->isApproved()) {
            $this->addFlash('warning', 'Не можна редагувати вже схвалений коментар');
            return $this->redirectToRoute('user_comments');
        }
        
        if ($request->isMethod('POST')) {
            $content = $request->request->get('content');
            
            if (empty($content)) {
                $this->addFlash('error', 'Коментар не може бути порожнім');
            } else {
                $comment->setContent($content);
                $entityManager->flush();
                
                $this->addFlash('success', 'Коментар успішно оновлено');
                return $this->redirectToRoute('user_comments');
            }
        }
        
        return $this->render('user/comment_edit.html.twig', [
            'comment' => $comment,
        ]);
    }
    
    #[Route('/{id}/delete', name: 'user_comment_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Comment $comment,
        EntityManagerInterface $entityManager
    ): Response
    {
        // Перевіряємо, чи коментар належить користувачу
        if ($comment->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Цей коментар не належить вам');
        }
        
        // Перевіряємо CSRF токен
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $comment->getId(), $token)) {
            $this->addFlash('error', 'Недійсний токен безпеки');
            return $this->redirectToRoute('user_comments');
        }
        
        // Видаляємо коментар
        $entityManager->remove($comment);
        $entityManager->flush();
        
        $this->addFlash('success', 'Коментар успішно видалено');
        return $this->redirectToRoute('user_comments');
    }
}