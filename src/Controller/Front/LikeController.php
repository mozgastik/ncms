<?php
// src/Controller/LikeController.php

namespace App\Controller\Front;

use App\Entity\Article\Like;
use App\Entity\Article\Article;
use App\Entity\Blog\BlogPost;
use App\Entity\Article\ArticleComment;
use App\Entity\Blog\BlogComment;
use App\Repository\LikeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class LikeController extends AbstractController
{
    #[Route('/like/{entityType}/{id}/{voteType}', name: 'app_like_vote', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function vote(
        string $entityType,
        int $id,
        string $voteType,
        Request $request,
        EntityManagerInterface $entityManager,
        LikeRepository $likeRepository
    ): JsonResponse
    {
        // Валідація entityType
        if (!in_array($entityType, [
            Like::TYPE_ARTICLE,
            Like::TYPE_BLOG_POST,
            Like::TYPE_COMMENT,
            Like::TYPE_BLOG_COMMENT
        ])) {
            return $this->json([
                'success' => false,
                'message' => 'Невірний тип сутності'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Валідація voteType
        if (!in_array($voteType, [Like::VOTE_LIKE, Like::VOTE_DISLIKE])) {
            return $this->json([
                'success' => false,
                'message' => 'Невірний тип голосу'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Перевірка CSRF токена
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('like_vote', $token)) {
            return $this->json([
                'success' => false,
                'message' => 'Недійсний токен безпеки'
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        // Перевіряємо, чи існує сутність
        if (!$this->entityExists($entityType, $id, $entityManager)) {
            return $this->json([
                'success' => false,
                'message' => 'Сутність не знайдена'
            ], Response::HTTP_NOT_FOUND);
        }

        // Перевіряємо, чи користувач вже голосував
        $existingVote = $likeRepository->findUserVote($user->getId(), $entityType, $id);

        if ($existingVote) {
            // Якщо голос той самий - видаляємо його
            if ($existingVote->getVoteType() === $voteType) {
                $entityManager->remove($existingVote);
                $entityManager->flush();
                
                $likes = $likeRepository->countLikes($entityType, $id);
                $dislikes = $likeRepository->countDislikes($entityType, $id);
                
                return $this->json([
                    'success' => true,
                    'message' => $voteType === Like::VOTE_LIKE ? 'Лайк видалено' : 'Дизлайк видалено',
                    'likes' => $likes,
                    'dislikes' => $dislikes,
                    'userLiked' => false,
                    'userDisliked' => false,
                    'action' => 'removed',
                ]);
            } else {
                // Якщо голос інший - змінюємо його
                $existingVote->setVoteType($voteType);
                $entityManager->flush();
                
                $likes = $likeRepository->countLikes($entityType, $id);
                $dislikes = $likeRepository->countDislikes($entityType, $id);
                
                return $this->json([
                    'success' => true,
                    'message' => $voteType === Like::VOTE_LIKE ? 'Лайк додано' : 'Дизлайк додано',
                    'likes' => $likes,
                    'dislikes' => $dislikes,
                    'userLiked' => $voteType === Like::VOTE_LIKE,
                    'userDisliked' => $voteType === Like::VOTE_DISLIKE,
                    'action' => 'changed',
                ]);
            }
        } else {
            // Додаємо новий голос
            $like = new Like();
            $like->setUser($user);
            $like->setEntityType($entityType);
            $like->setEntityId($id);
            $like->setVoteType($voteType);
            
            $entityManager->persist($like);
            $entityManager->flush();
            
            $likes = $likeRepository->countLikes($entityType, $id);
            $dislikes = $likeRepository->countDislikes($entityType, $id);
            
            return $this->json([
                'success' => true,
                'message' => $voteType === Like::VOTE_LIKE ? 'Лайк додано' : 'Дизлайк додано',
                'likes' => $likes,
                'dislikes' => $dislikes,
                'userLiked' => $voteType === Like::VOTE_LIKE,
                'userDisliked' => $voteType === Like::VOTE_DISLIKE,
                'action' => 'added',
            ]);
        }
    }

    /**
     * Перевіряє, чи існує сутність
     */
    private function entityExists(string $entityType, int $id, EntityManagerInterface $entityManager): bool
    {
        switch ($entityType) {
            case Like::TYPE_ARTICLE:
                $repository = $entityManager->getRepository(Article::class);
                break;
            case Like::TYPE_BLOG_POST:
                $repository = $entityManager->getRepository(BlogPost::class);
                break;
            case Like::TYPE_COMMENT:
                $repository = $entityManager->getRepository(ArticleComment::class);
                break;
            case Like::TYPE_BLOG_COMMENT:
                $repository = $entityManager->getRepository(BlogComment::class);
                break;
            default:
                return false;
        }
        
        return $repository->find($id) !== null;
    }

    /**
     * Отримати інформацію про голоси сутності
     */
    #[Route('/like/{entityType}/{id}/info', name: 'app_like_info', methods: ['GET'])]
    public function getVoteInfo(
        string $entityType,
        int $id,
        LikeRepository $likeRepository
    ): JsonResponse
    {
        $likes = $likeRepository->countLikes($entityType, $id);
        $dislikes = $likeRepository->countDislikes($entityType, $id);
        
        $userLiked = false;
        $userDisliked = false;
        
        if ($user = $this->getUser()) {
            $userVote = $likeRepository->findUserVote($user->getId(), $entityType, $id);
            if ($userVote) {
                $userLiked = $userVote->isLike();
                $userDisliked = $userVote->isDislike();
            }
        }
        
        return $this->json([
            'likes' => $likes,
            'dislikes' => $dislikes,
            'userLiked' => $userLiked,
            'userDisliked' => $userDisliked,
            'total' => $likes + $dislikes,
            'ratio' => $likes + $dislikes > 0 ? round($likes / ($likes + $dislikes) * 100, 1) : 0,
        ]);
    }
}