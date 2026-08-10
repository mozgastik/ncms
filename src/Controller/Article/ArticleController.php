<?php

namespace App\Controller\Article;

use App\Entity\Article\Article;
use App\Entity\Article\ArticleComment;
use App\Entity\Article\Category; 
use App\Entity\Article\Like;
use App\Form\CommentType;
use App\Repository\ArticleRepository;
use App\Repository\ArticleCommentRepository;
use App\Repository\CategoryRepository;
use App\Repository\LikeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;


class ArticleController extends AbstractController
{  
    #[Route('/admin/article/{id}/toggle-publish', name: 'admin_article_toggle_publish', methods: ['POST'])]
    public function togglePublish(Article $article, EntityManagerInterface $entityManager, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Перевірка CSRF токена
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('toggle_publish' . $article->getId(), $token)) {
            return $this->json([
                'success' => false,
                'message' => 'Недійсний токен безпеки'
            ], 400);
        }

        // Змінюємо статус між "опубліковано" та "чернетка"
        if ($article->getStatus() === Article::STATUS_PUBLISHED) {
            $article->setStatus(Article::STATUS_DRAFT);
            $message = 'Статтю переведено в чернетку';
        } else {
            $article->setStatus(Article::STATUS_PUBLISHED);
            if (!$article->getPublishedAt()) {
                $article->setPublishedAt(new \DateTime());
            }
            $message = 'Статтю опубліковано';
        }
        
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'status' => $article->getStatus(),
            'message' => $message
        ]);
    }
    
    #[Route('/articles', name: 'app_article_index')]
    public function index(
        ArticleRepository $articleRepository,
        CategoryRepository $categoryRepository,
        PaginatorInterface $paginator,
        Request $request,
        LikeRepository $likeRepository
    ): Response
    {
        $user = $this->getUser();
        
        $query = $articleRepository->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', Article::STATUS_PUBLISHED)
            ->orderBy('a.publishedAt', 'DESC')
            ->getQuery();

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            12
        );
        
        // Додаємо інформацію про лайки для кожної статті
        $articlesWithLikes = [];
        foreach ($pagination as $article) {
            $likesCount = $likeRepository->countLikesForArticle($article->getId());
            $dislikesCount = $likeRepository->countDislikesForArticle($article->getId());
            
            $userLiked = false;
            $userDisliked = false;
            
            if ($user) {
                $userVote = $likeRepository->findUserVoteForArticle($user, $article);
                if ($userVote) {
                    $userLiked = $userVote->isLike();
                    $userDisliked = !$userVote->isLike();
                }
            }
            
            $articlesWithLikes[] = [
                'article' => $article,
                'voteInfo' => [
                    'likes' => $likesCount,
                    'dislikes' => $dislikesCount,
                    'userLiked' => $userLiked,
                    'userDisliked' => $userDisliked,
                ]
            ];
        }

        // Отримуємо найпопулярніші та останні статті для бічної панелі
        $popularArticles = $articleRepository->findBy(
            ['status' => Article::STATUS_PUBLISHED],
            ['views' => 'DESC'],
            5
        );
        
        // Використовуємо ін'єктований репозиторій
        $categories = $categoryRepository->findAll();

        return $this->render('article/index.html.twig', [
            'pagination' => $pagination,
            'articlesWithLikes' => $articlesWithLikes,
            'popularArticles' => $popularArticles,
            'categories' => $categories,
        ]);
    }

    #[Route('/article/{slug}', name: 'app_article_show')]
    public function show(
        string $slug,
        Request $request,
        EntityManagerInterface $entityManager,
        ArticleRepository $articleRepository,
        ArticleCommentRepository $articleCommentRepository,
        LikeRepository $likeRepository
    ): Response
    {
        // Знаходимо опубліковану статтю
        $article = $articleRepository->findOneBy([
            'slug' => $slug,
            'status' => Article::STATUS_PUBLISHED
        ]);
        
        // Для адмінів дозволяємо перегляд неопублікованих
        if (!$article && $this->isGranted('ROLE_ADMIN')) {
            $article = $articleRepository->findOneBy(['slug' => $slug]);
        }
        
        if (!$article) {
            throw $this->createNotFoundException('Статтю не знайдено');
        }
        
        // Для не-адмінів перевіряємо статус публікації
        if ($article->getStatus() !== Article::STATUS_PUBLISHED && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException('Статтю не знайдено');
        }

        // Збільшуємо лічильник переглядів
        $article->incrementViews();
        $entityManager->persist($article);
        $entityManager->flush();

        // Отримуємо користувача
        $user = $this->getUser();
        $isAuthenticated = $user !== null;
        
        // Отримуємо інформацію про голоси через репозиторій
        $likesCount = $likeRepository->countLikesForArticle($article->getId());
        $dislikesCount = $likeRepository->countDislikesForArticle($article->getId());
        
        $userLiked = false;
        $userDisliked = false;
        
        if ($user) {
            $userVote = $likeRepository->findUserVoteForArticle($user, $article);
            if ($userVote) {
                $userLiked = $userVote->isLike();
                $userDisliked = !$userVote->isLike();
            }
        }
        
        $voteInfo = [
            'likes' => $likesCount,
            'dislikes' => $dislikesCount,
            'userLiked' => $userLiked,
            'userDisliked' => $userDisliked,
        ];

        // Створюємо форму для коментаря
        $comment = new ArticleComment();
        $comment->setArticle($article);
        
        // Встановлюємо користувача, якщо він авторизований
        if ($isAuthenticated) {
            $comment->setUser($user);
            $comment->setIsApproved(true); // Автоматично схвалюємо для авторизованих
        }
        
        $form = $this->createForm(CommentType::class, $comment, [
            'is_authenticated' => $isAuthenticated,
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Обробка для гостей
            if (!$isAuthenticated) {
                $comment->setAuthorName($form->get('authorName')->getData());
                $comment->setAuthorEmail($form->get('authorEmail')->getData());
                // Коментарі від гостей потребують модерації
                $comment->setIsApproved(false);
            }
            
            // Додаємо IP користувача
            $comment->setAuthorIp($request->getClientIp());
            $comment->setAuthorUserAgent($request->headers->get('User-Agent'));
            
            $entityManager->persist($comment);
            
            // Збільшуємо лічильник коментарів
            $article->incrementCommentCount();
            
            $entityManager->flush();

            if ($isAuthenticated) {
                $this->addFlash('success', 'Ваш коментар успішно додано!');
            } else {
                $this->addFlash('success', 'Ваш коментар успішно додано! Він з\'явиться після перевірки модератором.');
            }
            
            return $this->redirectToRoute('app_article_show', [
                'slug' => $article->getSlug(),
                '_fragment' => 'comments',
            ]);
        }

        // Отримуємо схвалені коментарі
        $comments = $articleCommentRepository->findApprovedCommentsByArticle($article);

        // Отримуємо пов'язані статті
        $relatedArticles = [];
        if ($article->getCategory()) {
            $relatedArticles = $articleRepository->findRelatedArticles($article, 3);
        }
        

        return $this->render('article/show.html.twig', [
            'article' => $article,
            'form' => $form->createView(),
            'comments' => $comments,
            'relatedArticles' => $relatedArticles,
            'voteInfo' => $voteInfo,
            'user' => $user,
        ]);
    }
    
    #[Route('/article/{id}/vote', name: 'app_article_vote', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function vote(
        Article $article,
        Request $request,
        EntityManagerInterface $entityManager,
        LikeRepository $likeRepository
    ): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Необхідна авторизація'
            ], 401);
        }

        // Перевірка CSRF токена
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('vote' . $article->getId(), $token)) {
            return $this->json([
                'success' => false,
                'message' => 'Недійсний токен безпеки'
            ], 400);
        }

        $action = $request->request->get('action'); // 'like' або 'dislike'
        $cancel = $request->request->getBoolean('cancel', false);

        // Знаходимо існуючий голос
        $existingLike = $likeRepository->findUserVoteForArticle($user, $article);

        if ($cancel) {
            // Скасувати голос
            if ($existingLike) {
                $entityManager->remove($existingLike);
                $entityManager->flush();
                
                return $this->json([
                    'success' => true,
                    'message' => 'Голос скасовано',
                    'likes' => $likeRepository->countLikesForArticle($article->getId()),
                    'dislikes' => $likeRepository->countDislikesForArticle($article->getId()),
                    'userVote' => null
                ]);
            }
            
            return $this->json([
                'success' => false,
                'message' => 'Голос не знайдено'
            ], 404);
        }

        // Визначаємо чи це лайк
        $isLike = $action === 'like';
        
        // Якщо вже є голос
        if ($existingLike) {
            // Якщо той самий тип голосу - скасовуємо
            if ($existingLike->isLike() === $isLike) {
                $entityManager->remove($existingLike);
                $entityManager->flush();
                
                $message = $action === 'like' ? 'Лайк скасовано' : 'Дизлайк скасовано';
                
                return $this->json([
                    'success' => true,
                    'message' => $message,
                    'likes' => $likeRepository->countLikesForArticle($article->getId()),
                    'dislikes' => $likeRepository->countDislikesForArticle($article->getId()),
                    'userVote' => null
                ]);
            }
            
            // Якщо змінюємо тип голосу
            $existingLike->setIsLike($isLike);
            $message = $action === 'like' ? 'Змінено на лайк' : 'Змінено на дизлайк';
        } else {
            // Новий голос
            $like = new Like();
            $like->setUser($user);
            $like->setArticle($article);
            $like->setIsLike($isLike);
            $entityManager->persist($like);
            
            $message = $action === 'like' ? 'Лайк додано' : 'Дизлайк додано';
        }
        
        $entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => $message,
            'likes' => $likeRepository->countLikesForArticle($article->getId()),
            'dislikes' => $likeRepository->countDislikesForArticle($article->getId()),
            'userVote' => $action
        ]);
    }

    #[Route('/article/{id}/vote-info', name: 'app_article_vote_info', methods: ['GET'])]
    public function getVoteInfo(Article $article, LikeRepository $likeRepository): JsonResponse
    {
        $user = $this->getUser();
        
        $likes = $likeRepository->countLikesForArticle($article->getId());
        $dislikes = $likeRepository->countDislikesForArticle($article->getId());
        
        $userVote = null;
        if ($user) {
            $vote = $likeRepository->findUserVoteForArticle($user, $article);
            
            if ($vote) {
                $userVote = $vote->isLike() ? 'like' : 'dislike';
            }
        }
        
        return $this->json([
            'success' => true,
            'likes' => $likes,
            'dislikes' => $dislikes,
            'userVote' => $userVote,
            'total' => $likes + $dislikes,
            'ratio' => $likes + $dislikes > 0 ? round($likes / ($likes + $dislikes) * 100, 1) : 0
        ]);
    }

#[Route('/article/comment/{id}/vote-info', name: 'app_article_comment_vote_info', methods: ['GET'])]
public function getCommentVoteInfo(ArticleComment $comment, LikeRepository $likeRepository): JsonResponse
{
    $user = $this->getUser();
    
    $likes = $likeRepository->countLikesForComment($comment->getId());
    $dislikes = $likeRepository->countDislikesForComment($comment->getId());
    
    $userVote = null;
    if ($user) {
        $vote = $likeRepository->findUserVoteForComment($user, $comment);
        
        if ($vote) {
            $userVote = $vote->isLike() ? 'like' : 'dislike';
        }
    }
    
    return $this->json([
        'success' => true,
        'likes' => $likes,
        'dislikes' => $dislikes,
        'userVote' => $userVote,
        'total' => $likes + $dislikes,
        'ratio' => $likes + $dislikes > 0 ? round($likes / ($likes + $dislikes) * 100, 1) : 0
    ]);
}


#[Route('/article/comment/{id}/vote', name: 'app_article_comment_vote', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function commentVote(
    ArticleComment $comment,
    Request $request,
    EntityManagerInterface $entityManager,
    LikeRepository $likeRepository
): JsonResponse
{
    $user = $this->getUser();
    
    if (!$user) {
        return $this->json([
            'success' => false,
            'message' => 'Необхідна авторизація'
        ], 401);
    }

    // Отримуємо action
    $action = $request->request->get('action');
    
    // Якщо action не в POST - пробуємо з GET
    if (empty($action)) {
        $action = $request->query->get('action');
    }
    
    // Якщо все ще пусто - за замовчуванням like
    if (empty($action)) {
        $action = 'like';
    }

    // Отримуємо токен
    $token = $request->request->get('_token');
    if (empty($token)) {
        $token = $request->query->get('_token');
    }
    
    // Перевірка CSRF (якщо токен є)
    if (!empty($token)) {
        if (!$this->isCsrfTokenValid('comment_vote' . $comment->getId(), $token)) {
            return $this->json([
                'success' => false,
                'message' => 'Недійсний токен безпеки'
            ], 400);
        }
    }

    $cancel = $request->request->getBoolean('cancel', false);

    // Знаходимо існуючий голос
    $existingLike = $likeRepository->findUserVoteForComment($user, $comment);

    if ($cancel) {
        if ($existingLike) {
            $entityManager->remove($existingLike);
            $entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Голос скасовано',
                'likes' => $likeRepository->countLikesForComment($comment->getId()),
                'dislikes' => $likeRepository->countDislikesForComment($comment->getId()),
                'userVote' => null
            ]);
        }
        
        return $this->json([
            'success' => false,
            'message' => 'Голос не знайдено'
        ], 404);
    }

    $isLike = $action === 'like';
    
    if ($existingLike) {
        if ($existingLike->isLike() === $isLike) {
            $entityManager->remove($existingLike);
            $entityManager->flush();
            
            $message = $action === 'like' ? 'Лайк скасовано' : 'Дизлайк скасовано';
            
            return $this->json([
                'success' => true,
                'message' => $message,
                'likes' => $likeRepository->countLikesForComment($comment->getId()),
                'dislikes' => $likeRepository->countDislikesForComment($comment->getId()),
                'userVote' => null
            ]);
        }
        
        $existingLike->setIsLike($isLike);
        $message = $action === 'like' ? 'Змінено на лайк' : 'Змінено на дизлайк';
    } else {
        $like = new Like();
        $like->setUser($user);
        $like->setArticleComment($comment);
        $like->setIsLike($isLike);
        $entityManager->persist($like);
        
        $message = $action === 'like' ? 'Лайк додано' : 'Дизлайк додано';
    }
    
    $entityManager->flush();
    
    return $this->json([
        'success' => true,
        'message' => $message,
        'likes' => $likeRepository->countLikesForComment($comment->getId()),
        'dislikes' => $likeRepository->countDislikesForComment($comment->getId()),
        'userVote' => $action
    ]);
}
    
    // Метод для пошуку статей
    #[Route('/articles/search', name: 'app_article_search')]
    public function search(
        Request $request,
        ArticleRepository $articleRepository,
        PaginatorInterface $paginator,
        LikeRepository $likeRepository
    ): Response
    {
        $query = $request->query->get('q', '');
        $user = $this->getUser();
        
        if (empty($query)) {
            return $this->redirectToRoute('app_article_index');
        }
        
        $articlesQuery = $articleRepository->createSearchQuery($query);
        
        $pagination = $paginator->paginate(
            $articlesQuery,
            $request->query->getInt('page', 1),
            12
        );
        
        // Додаємо інформацію про лайки
        $articlesWithLikes = [];
        foreach ($pagination as $article) {
            $likesCount = $likeRepository->countLikesForArticle($article->getId());
            $dislikesCount = $likeRepository->countDislikesForArticle($article->getId());
            
            $userLiked = false;
            $userDisliked = false;
            
            if ($user) {
                $userVote = $likeRepository->findUserVoteForArticle($user, $article);
                if ($userVote) {
                    $userLiked = $userVote->isLike();
                    $userDisliked = !$userVote->isLike();
                }
            }
            
            $articlesWithLikes[] = [
                'article' => $article,
                'voteInfo' => [
                    'likes' => $likesCount,
                    'dislikes' => $dislikesCount,
                    'userLiked' => $userLiked,
                    'userDisliked' => $userDisliked,
                ]
            ];
        }

        return $this->render('article/search.html.twig', [
            'pagination' => $pagination,
            'articlesWithLikes' => $articlesWithLikes,
            'searchQuery' => $query,
        ]);
    }
    
    // Метод для перегляду статей за категорією
    #[Route('/category/{slug}', name: 'app_category_show')]
    public function category(
        string $slug,
        ArticleRepository $articleRepository,
        CategoryRepository $categoryRepository, // ← Додайте ін'єкцію CategoryRepository
        PaginatorInterface $paginator,
        Request $request,
        LikeRepository $likeRepository
    ): Response
    {
        $user = $this->getUser();
        
        // Використовуємо ін'єктований репозиторій
        $category = $categoryRepository->findOneBy(['slug' => $slug]);
        
        if (!$category) {
            throw $this->createNotFoundException('Категорію не знайдено');
        }
        
        $query = $articleRepository->createQueryBuilder('a')
            ->andWhere('a.category = :category')
            ->andWhere('a.status = :status')
            ->setParameter('category', $category)
            ->setParameter('status', Article::STATUS_PUBLISHED)
            ->orderBy('a.publishedAt', 'DESC')
            ->getQuery();

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            12
        );
        
        // Додаємо інформацію про лайки
        $articlesWithLikes = [];
        foreach ($pagination as $article) {
            $likesCount = $likeRepository->countLikesForArticle($article->getId());
            $dislikesCount = $likeRepository->countDislikesForArticle($article->getId());
            
            $userLiked = false;
            $userDisliked = false;
            
            if ($user) {
                $userVote = $likeRepository->findUserVoteForArticle($user, $article);
                if ($userVote) {
                    $userLiked = $userVote->isLike();
                    $userDisliked = !$userVote->isLike();
                }
            }
            
            $articlesWithLikes[] = [
                'article' => $article,
                'voteInfo' => [
                    'likes' => $likesCount,
                    'dislikes' => $dislikesCount,
                    'userLiked' => $userLiked,
                    'userDisliked' => $userDisliked,
                ]
            ];
        }

        return $this->render('article/category.html.twig', [
            'category' => $category,
            'pagination' => $pagination,
            'articlesWithLikes' => $articlesWithLikes,
        ]);
    }


#[Route('/article/{id}/comment/ajax', name: 'app_article_comment_ajax', methods: ['POST'])]
public function addCommentAjax(
    Request $request,
    Article $article,
    EntityManagerInterface $entityManager,
    HtmlSanitizerInterface $htmlSanitizer
): JsonResponse
{
    // Перевіряємо чи це AJAX запит
    if (!$request->isXmlHttpRequest()) {
        return $this->json([
            'success' => false,
            'message' => 'Некоректний запит'
        ], 400);
    }

    try {
        $user = $this->getUser();
        $isAuthenticated = $user !== null;

        // Отримуємо дані
        $allData = $request->request->all();
        
        if (empty($allData)) {
            $content = $request->getContent();
            if (!empty($content)) {
                $jsonData = json_decode($content, true);
                if ($jsonData && isset($jsonData['comment'])) {
                    $allData = $jsonData;
                }
            }
        }
        
        $commentData = isset($allData['comment']) ? $allData['comment'] : [];
        
        if (empty($commentData)) {
            $commentData = [
                'content' => $request->request->get('content', ''),
                'authorName' => $request->request->get('authorName', ''),
                'authorEmail' => $request->request->get('authorEmail', ''),
            ];
        }
        
        if (empty($commentData)) {
            return $this->json([
                'success' => false,
                'message' => 'Не отримано даних коментаря'
            ], 400);
        }
        
        $content = isset($commentData['content']) ? $commentData['content'] : '';
        $authorName = isset($commentData['authorName']) ? $commentData['authorName'] : '';
        $authorEmail = isset($commentData['authorEmail']) ? $commentData['authorEmail'] : '';
        
        // Очищуємо HTML
        $content = $htmlSanitizer->sanitize($content);
        
        $textContent = strip_tags($content);
        if (empty($textContent) || strlen($textContent) < 2) {
            return $this->json([
                'success' => false,
                'message' => 'Текст коментаря занадто короткий'
            ], 400);
        }
        
        if (!$isAuthenticated && empty($authorName)) {
            return $this->json([
                'success' => false,
                'message' => 'Будь ласка, введіть ваше ім\'я'
            ], 400);
        }

        // Створюємо коментар
        $comment = new ArticleComment();
        $comment->setArticle($article);
        $comment->setContent($content);
        
        if ($isAuthenticated) {
            $comment->setUser($user);
            $comment->setIsApproved(true);
        } else {
            $comment->setAuthorName($authorName);
            $comment->setAuthorEmail($authorEmail);
            $comment->setIsApproved(false);
        }
        
        $comment->setAuthorIp($request->getClientIp());
        $comment->setAuthorUserAgent($request->headers->get('User-Agent'));
        
        $entityManager->persist($comment);
        $article->incrementCommentCount();
        $entityManager->flush();

        // ============================================
        // ВИПРАВЛЕНО: avatar всередині user
        // ============================================
        $userData = null;
        if ($isAuthenticated) {
            $userData = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'avatar' => $user->getAvatar() // ← avatar всередині user
            ];
        }

        return $this->json([
            'success' => true,
            'message' => $isAuthenticated ? 'Коментар додано!' : 'Коментар додано! Він з\'явиться після перевірки модератором.',
            'comment' => [
                'id' => $comment->getId(),
                'authorName' => $comment->getDisplayName(),
                'content' => $comment->getContent(),
                'createdAt' => $comment->getCreatedAt()->format('c'),
                'likes' => 0,
                'user' => $userData, // ← avatar всередині user
                'isAuthor' => $isAuthenticated && $comment->getUser() === $article->getAuthor(),
                'canDelete' => $this->isGranted('ROLE_ADMIN') || ($isAuthenticated && $comment->getUser() === $user),
                'csrfToken' => $this->container->get('security.csrf.token_manager')
                    ->getToken('comment_delete' . $comment->getId())->getValue()
            ]
        ]);

    } catch (\Exception $e) {
        error_log('Comment AJAX error: ' . $e->getMessage());
        error_log('Trace: ' . $e->getTraceAsString());
        
        return $this->json([
            'success' => false,
            'message' => 'Помилка: ' . $e->getMessage()
        ], 500);
    }
}

#[Route('/article/comment/{id}/delete', name: 'app_article_comment_delete', methods: ['POST', 'DELETE'])]
public function deleteComment(
    ArticleComment $comment,
    Request $request,
    EntityManagerInterface $entityManager
): JsonResponse
{
    $user = $this->getUser();
    
    if (!$user) {
        return $this->json([
            'success' => false,
            'message' => 'Необхідна авторизація'
        ], 401);
    }

    $isAdmin = $this->isGranted('ROLE_ADMIN');
    $isAuthor = $comment->getUser() && $comment->getUser()->getId() === $user->getId();
    
    if (!$isAdmin && !$isAuthor) {
        return $this->json([
            'success' => false,
            'message' => 'У вас немає прав на видалення цього коментаря'
        ], 403);
    }

     // Отримуємо токен
    $token = $request->request->get('_token');
    if (empty($token)) {
        $token = $request->query->get('_token');
    }
    // Перевірка CSRF (якщо токен є)
    if (!empty($token)) {
        if (!$this->isCsrfTokenValid('comment_delete' . $comment->getId(), $token)) {
            return $this->json([
                'success' => false,
                'message' => 'Недійсний токен безпеки'
            ], 400);
        }
    }

    try {
        $commentId = $comment->getId();
        $entityManager->remove($comment);
        $entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Коментар успішно видалено',
            'commentId' => $commentId
        ]);
        
    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Помилка видалення коментаря'
        ], 500);
    }
}

}