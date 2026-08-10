<?php

namespace App\Controller\Article;

use App\Entity\Article\Article;
use App\Entity\User\User;
use App\Entity\Article\ArticleLike;
use App\Entity\Article\ArticleDislike;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ArticleLikeController extends AbstractController
{
    #[Route('/article/{id}/like', name: 'app_article_like', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function like(Article $article, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Перевіряємо CSRF токен
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('article_like', $submittedToken)) {
            return $this->json([
                'success' => false,
                'message' => 'Недійсний токен безпеки'
            ], Response::HTTP_FORBIDDEN);
        }

        // Перевіряємо, чи користувач вже поставив лайк
        $existingLike = $entityManager->getRepository(ArticleLike::class)->findOneBy([
            'article' => $article,
            'user' => $user
        ]);

        // Перевіряємо, чи користувач вже поставив дизлайк
        $existingDislike = $entityManager->getRepository(ArticleDislike::class)->findOneBy([
            'article' => $article,
            'user' => $user
        ]);

        $message = '';
        $liked = false;
        
        if ($existingLike) {
            // Якщо вже лайкнуто - видаляємо лайк
            $entityManager->remove($existingLike);
            $article->decrementLikeCount();
            $liked = false;
            $message = 'Лайк видалено';
        } else {
            // Додаємо лайк
            $like = new ArticleLike();
            $like->setArticle($article);
            $like->setUser($user);
            $entityManager->persist($like);
            $article->incrementLikeCount();
            $liked = true;
            $message = 'Лайк додано';

            // Видаляємо дизлайк, якщо він був
            if ($existingDislike) {
                $entityManager->remove($existingDislike);
                $article->decrementDislikeCount();
            }
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => $message,
            'likes' => $article->getLikeCount(),
            'dislikes' => $article->getDislikeCount() ?? 0,
            'liked' => $liked,
            'disliked' => $existingDislike ? true : false,
        ]);
    }

    #[Route('/article/{id}/dislike', name: 'app_article_dislike', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function dislike(Article $article, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Перевіряємо CSRF токен
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('article_dislike', $submittedToken)) {
            return $this->json([
                'success' => false,
                'message' => 'Недійсний токен безпеки'
            ], Response::HTTP_FORBIDDEN);
        }

        // Перевіряємо, чи користувач вже поставив дизлайк
        $existingDislike = $entityManager->getRepository(ArticleDislike::class)->findOneBy([
            'article' => $article,
            'user' => $user
        ]);

        // Перевіряємо, чи користувач вже поставив лайк
        $existingLike = $entityManager->getRepository(ArticleLike::class)->findOneBy([
            'article' => $article,
            'user' => $user
        ]);

        $message = '';
        $disliked = false;
        
        if ($existingDislike) {
            // Якщо вже дизлайкнуто - видаляємо
            $entityManager->remove($existingDislike);
            $article->decrementDislikeCount();
            $disliked = false;
            $message = 'Дизлайк видалено';
        } else {
            // Додаємо дизлайк
            $dislike = new ArticleDislike();
            $dislike->setArticle($article);
            $dislike->setUser($user);
            $entityManager->persist($dislike);
            $article->incrementDislikeCount();
            $disliked = true;
            $message = 'Дизлайк додано';

            // Видаляємо лайк, якщо він був
            if ($existingLike) {
                $entityManager->remove($existingLike);
                $article->decrementLikeCount();
            }
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => $message,
            'likes' => $article->getLikeCount(),
            'dislikes' => $article->getDislikeCount() ?? 0,
            'liked' => $existingLike ? true : false,
            'disliked' => $disliked,
        ]);
    }
}