<?php

namespace App\EventSubscriber;

use App\Entity\Article\Article;
use App\Entity\Article\Comment;
use App\Entity\User\User;
use App\Service\AdminNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Bundle\SecurityBundle\Security; // ← ВАЖЛИВО: інший простір імен

class NotificationEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AdminNotificationService $notificationService,
        private Security $security, // ← тепер це правильний тип
        private EntityManagerInterface $em
    ) {}


    public static function getSubscribedEvents(): array
    {
        return [
            // Статті
            'article.created' => 'onArticleCreated',
            'article.updated' => 'onArticleUpdated',
            'article.published' => 'onArticlePublished',
            'article.archived' => 'onArticleArchived',
            'article.deleted' => 'onArticleDeleted',
            
            // Коментарі
            'comment.created' => 'onCommentCreated',
            'comment.approved' => 'onCommentApproved',
            'comment.rejected' => 'onCommentRejected',
            'comment.spam' => 'onCommentSpam',
            
            // Користувачі
            'user.registered' => 'onUserRegistered',
            'user.banned' => 'onUserBanned',
        ];
    }

    /**
     * Нова стаття (чернетка)
     */
    public function onArticleCreated(Article $article): void
    {
        $actor = $this->security->getUser();
        
        // Автору
        $this->notificationService->sendToUser(
            $article->getAuthor(),
            'Чернетка створена',
            sprintf('Стаття "%s" збережена як чернетка', $article->getTitle()),
            'info',
            $actor,
            [
                'action' => 'create',
                'entity_type' => 'Article',
                'entity_id' => $article->getId(),
                'link' => $this->notificationService->createEntityLink('Article', $article->getId()),
            ]
        );

        // Всім адмінам про нову статтю на модерацію
        if ($article->isPending()) {
            $this->notificationService->sendToAdmins(
                'Нова стаття на модерації',
                sprintf('Користувач %s створив статтю "%s"', 
                    $article->getAuthor()->getUsername(), 
                    $article->getTitle()
                ),
                'warning',
                $article->getAuthor(),
                [
                    'action' => 'pending',
                    'entity_type' => 'Article',
                    'entity_id' => $article->getId(),
                    'link' => $this->notificationService->createEntityLink('Article', $article->getId()),
                ]
            );
        }
    }

    /**
     * Стаття опублікована
     */
    public function onArticlePublished(Article $article): void
    {
        $actor = $this->security->getUser();
        
        // Автору
        $this->notificationService->sendToUser(
            $article->getAuthor(),
            'Стаття опублікована! 🎉',
            sprintf('Ваша стаття "%s" успішно опублікована', $article->getTitle()),
            'success',
            $actor,
            [
                'action' => 'publish',
                'entity_type' => 'Article',
                'entity_id' => $article->getId(),
                'link' => $this->notificationService->createEntityLink('Article', $article->getId()),
            ]
        );

        // Всім підписникам категорії (якщо є)
        if ($article->getCategory()) {
            // Логіка для підписників
        }
    }

    /**
     * Новий коментар
     */
    public function onCommentCreated(Comment $comment): void
    {
        $actor = $this->security->getUser();
        $article = $comment->getArticle();
        
        // Автору статті
        if ($article->getAuthor() !== $comment->getAuthor()) {
            $this->notificationService->sendToUser(
                $article->getAuthor(),
                'Новий коментар до вашої статті',
                sprintf('Користувач %s залишив коментар: "%s"', 
                    $comment->getAuthorName() ?? $comment->getUser()?->getUsername() ?? 'Анонім',
                    $comment->getContent()|slice(0, 50)
                ),
                $comment->isApproved() ? 'info' : 'warning',
                $comment->getUser(),
                [
                    'action' => $comment->isApproved() ? 'comment' : 'comment_pending',
                    'entity_type' => 'Comment',
                    'entity_id' => $comment->getId(),
                    'link' => $this->notificationService->createEntityLink('Article', $article->getId()),
                ]
            );
        }

        // Всім адмінам якщо коментар на модерації
        if (!$comment->isApproved()) {
            $this->notificationService->sendToAdmins(
                'Коментар потребує модерації',
                sprintf('Новий коментар до статті "%s"', $article->getTitle()),
                'warning',
                $comment->getUser(),
                [
                    'action' => 'moderate',
                    'entity_type' => 'Comment',
                    'entity_id' => $comment->getId(),
                    'link' => $this->router->generate('admin_comment_index'),
                ]
            );
        }
    }

    /**
     * Коментар схвалено
     */
    public function onCommentApproved(Comment $comment): void
    {
        // Автору коментаря
        if ($comment->getUser()) {
            $this->notificationService->sendToUser(
                $comment->getUser(),
                'Ваш коментар схвалено',
                sprintf('Коментар до статті "%s" опубліковано', $comment->getArticle()->getTitle()),
                'success',
                null,
                [
                    'action' => 'approved',
                    'entity_type' => 'Comment',
                    'entity_id' => $comment->getId(),
                    'link' => $this->router->generate('app_article_show', ['slug' => $comment->getArticle()->getSlug()]),
                ]
            );
        }
    }

    /**
     * Новий користувач зареєструвався
     */
    public function onUserRegistered(User $user): void
    {
        // Вітання новому користувачу
        $this->notificationService->sendToUser(
            $user,
            'Ласкаво просимо! 🎉',
            'Дякуємо за реєстрацію на нашому порталі',
            'success'
        );

        // Всім адмінам про нового користувача
        $this->notificationService->sendToAdmins(
            'Новий користувач',
            sprintf('Зареєструвався користувач: %s (%s)', 
                $user->getUsername(), 
                $user->getEmail()
            ),
            'info',
            $user
        );
    }

    /**
     * Користувача заблоковано
     */
    public function onUserBanned(User $user): void
    {
        $actor = $this->security->getUser();
        
        // Самому користувачу
        $this->notificationService->sendToUser(
            $user,
            'Ваш акаунт заблоковано',
            'Зверніться до адміністрації для деталей',
            'error',
            $actor
        );

        // Всім адмінам
        $this->notificationService->sendToAdmins(
            'Користувача заблоковано',
            sprintf('Користувач %s був заблокований адміністратором %s',
                $user->getUsername(),
                $actor?->getUsername() ?? 'System'
            ),
            'warning',
            $actor
        );
    }
}