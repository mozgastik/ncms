<?php
// src/Controller/Admin/CommentController.php

namespace App\Controller\Admin;

use App\Entity\Article\ArticleComment;
use App\Repository\ArticleCommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CommentController extends AbstractController
{
    #[Route('/admin/comments', name: 'admin_comment_index', methods: ['GET'])]
    public function index(Request $request, ArticleCommentRepository $commentRepository): Response
    {
        $filter = $request->query->get('filter');
        
        $criteria = [];
        if ($filter === 'pending') {
            $criteria = ['isApproved' => false, 'isSpam' => false];
        } elseif ($filter === 'approved') {
            $criteria = ['isApproved' => true];
        } elseif ($filter === 'spam') {
            $criteria = ['isSpam' => true];
        }
        
        $comments = $commentRepository->findBy(
            $criteria,
            ['createdAt' => 'DESC']
        );
        
        $pendingCount = $commentRepository->count([
            'isApproved' => false,
            'isSpam' => false
        ]);
        
        return $this->render('admin/comment/index.html.twig', [
            'comments' => $comments,
            'pending_count' => $pendingCount,
        ]);
    }
    
    #[Route('/admin/comments/{id}/approve', name: 'admin_comment_approve', methods: ['POST'])]
    public function approve(Request $request, ArticleComment $comment, EntityManagerInterface $entityManager): Response
    {
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('approve' . $comment->getId(), $submittedToken)) {
            $this->addFlash('error', 'Недійсний CSRF токен');
            return $this->redirectToRoute('admin_comment_index');
        }
        
        $comment->setIsApproved(true);
        $comment->setIsSpam(false);
        $entityManager->flush();
        
        $this->addFlash('success', 'Коментар схвалено');
        
        return $this->redirectToRoute('admin_comment_index');
    }
    
    #[Route('/admin/comments/{id}/spam', name: 'admin_comment_spam', methods: ['POST'])]
    public function markAsSpam(Request $request, ArticleComment $comment, EntityManagerInterface $entityManager): Response
    {
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('spam' . $comment->getId(), $submittedToken)) {
            $this->addFlash('error', 'Недійсний CSRF токен');
            return $this->redirectToRoute('admin_comment_index');
        }
        
        $comment->setIsSpam(true);
        $comment->setIsApproved(false);
        $entityManager->flush();
        
        $this->addFlash('success', 'Коментар позначено як спам');
        
        return $this->redirectToRoute('admin_comment_index');
    }
    
    #[Route('/admin/comments/{id}/unspam', name: 'admin_comment_unspam', methods: ['POST'])]
    public function unmarkAsSpam(Request $request, ArticleComment $comment, EntityManagerInterface $entityManager): Response
    {
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('unspam' . $comment->getId(), $submittedToken)) {
            $this->addFlash('error', 'Недійсний CSRF токен');
            return $this->redirectToRoute('admin_comment_index');
        }
        
        $comment->setIsSpam(false);
        $entityManager->flush();
        
        $this->addFlash('success', 'Коментар відновлено зі спаму');
        
        return $this->redirectToRoute('admin_comment_index');
    }
    
    #[Route('/admin/comments/{id}/delete', name: 'admin_comment_delete', methods: ['POST'])]
    public function delete(Request $request, ArticleComment $comment, EntityManagerInterface $entityManager): Response
    {
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $comment->getId(), $submittedToken)) {
            $this->addFlash('error', 'Недійсний CSRF токен');
            return $this->redirectToRoute('admin_comment_index');
        }
        
        $entityManager->remove($comment);
        $entityManager->flush();
        
        $this->addFlash('success', 'Коментар видалено');
        
        return $this->redirectToRoute('admin_comment_index');
    }
}