<?php

namespace App\Controller\Blog;

use App\Entity\Blog\BlogPost;
use App\Entity\Blog\BlogComment;
use App\Entity\Article\Category;
use App\Repository\CategoryRepository;
use App\Entity\Admin\Tag;
use App\Form\BlogCommentType;
use App\Repository\BlogPostRepository;
use App\Repository\LikeRepository;
use App\Repository\TagRepository;
use App\Service\ReadingTimeCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/blog')]
class BlogController extends AbstractController
{
    public function __construct(
        private BlogPostRepository $blogPostRepository,
        private LikeRepository $likeRepository,
        private CategoryRepository $categoryRepository,
        private TagRepository $TagRepository,
        private EntityManagerInterface $entityManager,
        private ReadingTimeCalculator $readingTimeCalculator
    ) {}

    /**
     * Список усіх опублікованих блогів
     */
    #[Route('/', name: 'blog_index')]
public function index(Request $request, PaginatorInterface $paginator): Response
{
    $queryBuilder = $this->blogPostRepository->createQueryBuilder('bp')
        ->where('bp.status = :status')
        ->andWhere('bp.publishedAt <= :now OR bp.publishedAt IS NULL')
        ->setParameter('status', BlogPost::STATUS_PUBLISHED)
        ->setParameter('now', new \DateTimeImmutable())
        ->orderBy('bp.publishedAt', 'DESC');

    $pagination = $paginator->paginate(
        $queryBuilder,
        $request->query->getInt('page', 1),
        12
    );

    // Додаємо інформацію про лайки для кожного блог-поста
    $blogPostsWithLikes = [];
    foreach ($pagination as $blogPost) {
        $likesCount = $this->likeRepository->countLikesForBlogPost($blogPost->getId());
        $dislikesCount = $this->likeRepository->countDislikesForBlogPost($blogPost->getId());
        
        $blogPostsWithLikes[] = [
            'blogPost' => $blogPost,
            'likes' => $likesCount,
            'dislikes' => $dislikesCount,
        ];
    }

    // Отримуємо популярні блоги для сайдбара
    $popularPosts = $this->blogPostRepository->findPopular(5);
    
    // Теж додаємо лайки для популярних постів
    $popularPostsWithLikes = [];
    foreach ($popularPosts as $blogPost) {
        $likesCount = $this->likeRepository->countLikesForBlogPost($blogPost->getId());
        
        $popularPostsWithLikes[] = [
            'blogPost' => $blogPost,
            'likes' => $likesCount,
        ];
    }
    
    // Отримуємо всі категорії
    $categories = $this->categoryRepository->findAll();
    
    // Отримуємо популярні теги
    $tags = $this->TagRepository->findPopularTags(20);

    return $this->render('blog/index.html.twig', [
        'pagination' => $pagination,
        'blogPostsWithLikes' => $blogPostsWithLikes, // ← Додаємо це
        'popularPosts' => $popularPosts,
        'popularPostsWithLikes' => $popularPostsWithLikes, // ← І це
        'categories' => $categories,
        'tags' => $tags,
    ]);
}


/**
 * Перегляд конкретного блогу
 */
#[Route('/{slug}', name: 'blog_show', methods: ['GET'])] // Тільки GET
public function show(string $slug): Response
{
    $blogPost = $this->blogPostRepository->findOneBy([
        'slug' => $slug,
        'status' => BlogPost::STATUS_PUBLISHED
    ]);
    
    if (!$blogPost) {
        throw $this->createNotFoundException('Блог-пост не знайдено');
    }
    
    // Збільшуємо перегляди
    $blogPost->incrementViews();
    $this->entityManager->flush();
    
    // Отримуємо інформацію про лайки
    $likesCount = $this->likeRepository->countLikesForBlogPost($blogPost->getId());
    $dislikesCount = $this->likeRepository->countDislikesForBlogPost($blogPost->getId());
    
    // Перевіряємо, чи користувач вже голосував
    $userVote = null;
    if ($this->getUser()) {
        $userVote = $this->likeRepository->findUserVoteForBlogPost($this->getUser(), $blogPost);
    }
    
    $voteInfo = [
        'likes' => $likesCount,
        'dislikes' => $dislikesCount,
        'userLiked' => $userVote && $userVote->isLike(),
        'userDisliked' => $userVote && $userVote->isDislike(),
    ];
    
    // Отримуємо пов'язані пости
    $relatedPosts = [];
    if ($blogPost->getCategory()) {
        $relatedPosts = $this->blogPostRepository->findRelatedPosts($blogPost, 3);
    }
    
    // Створюємо форму коментаря (тільки для відображення)
    $comment = new BlogComment();
    if ($this->getUser()) {
        $comment->setUser($this->getUser());
    }
    $comment->setBlogPost($blogPost);
    
    $commentForm = $this->createForm(BlogCommentType::class, $comment, [
        'action' => $this->generateUrl('blog_comment', ['slug' => $blogPost->getSlug()]),
        'method' => 'POST',
    ]);
    
    // Отримуємо схвалені коментарі
    $approvedComments = $blogPost->getComments()->filter(function($comment) {
        return $comment->isApproved();
    });
    
    return $this->render('blog/show.html.twig', [
        'blogPost' => $blogPost,
        'voteInfo' => $voteInfo,
        'relatedPosts' => $relatedPosts,
        'commentForm' => $commentForm->createView(),
        'approvedComments' => $approvedComments,
        'categories' => $this->categoryRepository->findAll(),
        'popularTags' => $this->TagRepository->findPopularTags(10),
    ]);
    }
/**
 * Додати коментар (для авторизованих і неавторизованих)
 */
#[Route('/{slug}/comment', name: 'blog_comment', methods: ['POST'])]
public function addComment(string $slug, Request $request): Response
{
    $blogPost = $this->blogPostRepository->findOneBy([
        'slug' => $slug,
        'status' => BlogPost::STATUS_PUBLISHED
    ]);
    
    if (!$blogPost) {
        throw $this->createNotFoundException('Блог-пост не знайдено');
    }
    
    $comment = new BlogComment();
    $comment->setBlogPost($blogPost);
    $comment->setCreatedAt(new \DateTimeImmutable());
    $comment->setIsApproved(false); // або false, якщо потрібна модерація
    
    // Якщо користувач авторизований
    if ($this->getUser()) {
        $comment->setUser($this->getUser());
    }
    
    $form = $this->createForm(BlogCommentType::class, $comment);
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
        $this->entityManager->persist($comment);
        $this->entityManager->flush();
        
        $this->addFlash('success', 'Дякуємо! Ваш коментар додано.');
    } else {
        // Збираємо помилки
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        
        if (!empty($errors)) {
            $this->addFlash('error', implode(', ', $errors));
        } else {
            $this->addFlash('error', 'Помилка при додаванні коментаря. Перевірте правильність заповнення полів.');
        }
    }
    
    return $this->redirectToRoute('blog_show', ['slug' => $slug]);
   }
    /**
     * Блоги за категорією
     */
   #[Route('/category/{slug}', name: 'blog_category')]
public function category(string $slug, Request $request, PaginatorInterface $paginator): Response
{
    $category = $this->categoryRepository->findOneBy(['slug' => $slug]);

    if (!$category) {
        throw $this->createNotFoundException('Категорія не знайдена');
    }

    $queryBuilder = $this->blogPostRepository->createQueryBuilder('bp')
        ->where('bp.status = :status')
        ->andWhere('bp.category = :category')
        ->andWhere('bp.publishedAt <= :now OR bp.publishedAt IS NULL')
        ->setParameter('status', BlogPost::STATUS_PUBLISHED)
        ->setParameter('category', $category)
        ->setParameter('now', new \DateTimeImmutable())
        ->orderBy('bp.publishedAt', 'DESC');

    $pagination = $paginator->paginate(
        $queryBuilder,
        $request->query->getInt('page', 1),
        12
    );

    return $this->render('blog/category.html.twig', [
        'category' => $category,
        'pagination' => $pagination,
    ]);
}

    /**
     * Блоги за тегом
     */
    #[Route('/tag/{slug}', name: 'blog_tag')]
    public function tag(string $slug, Request $request, PaginatorInterface $paginator): Response
    {
        $tag = $this->TagRepository->findOneBy(['slug' => $slug]);

        if (!$tag) {
            throw $this->createNotFoundException('Тег не знайдений');
        }

        $queryBuilder = $this->blogPostRepository->createQueryBuilder('bp')
            ->join('bp.tags', 't')
            ->where('bp.status = :status')
            ->andWhere('t = :tag')
            ->andWhere('bp.publishedAt <= :now OR bp.publishedAt IS NULL')
            ->setParameter('status', BlogPost::STATUS_PUBLISHED)
            ->setParameter('tag', $tag)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('bp.publishedAt', 'DESC');

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            12
        );

        return $this->render('blog/tag.html.twig', [
            'tag' => $tag,
            'pagination' => $pagination,
        ]);
    }

    /**
     * Пошук блогів
     */
    #[Route('/search', name: 'blog_search')]
    public function search(Request $request, PaginatorInterface $paginator): Response
    {
        $query = $request->query->get('q', '');
        
        if (empty($query)) {
            return $this->redirectToRoute('blog_index');
        }

        $queryBuilder = $this->blogPostRepository->createSearchQueryBuilder($query);

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            12
        );

        return $this->render('blog/search.html.twig', [
            'query' => $query,
            'pagination' => $pagination,
            'resultsCount' => $pagination->getTotalItemCount(),
        ]);
    }

    /**
 * Лайк блогу (AJAX)
 */
#[Route('/{id}/like', name: 'blog_like', methods: ['POST'])]
public function like(BlogPost $blogPost, Request $request, LikeRepository $likeRepository): JsonResponse
{
    if (!$this->isCsrfTokenValid('like' . $blogPost->getId(), $request->request->get('_token'))) {
        return $this->json(['success' => false, 'error' => 'Invalid CSRF token'], 400);
    }

    $user = $this->getUser();
    if (!$user) {
        return $this->json(['success' => false, 'error' => 'Необхідно увійти в систему'], 401);
    }

    // Шукаємо існуючий лайк від цього користувача для цього блог-поста
    $existingVote = $likeRepository->findOneBy([
        'user' => $user,
        'blogPost' => $blogPost
    ]);

    $message = '';
    
    if ($existingVote) {
        if ($existingVote->isLike()) {
            // Якщо вже лайк - видаляємо
            $this->entityManager->remove($existingVote);
            $message = 'Лайк видалено';
        } else {
            // Якщо був дизлайк - змінюємо на лайк
            $existingVote->setIsLike(true);
            $message = 'Дизлайк змінено на лайк';
        }
    } else {
        // Додаємо новий лайк
        $vote = new \App\Entity\Like();
        $vote->setUser($user);
        $vote->setBlogPost($blogPost);
        $vote->setIsLike(true);
        $vote->setCreatedAt(new \DateTimeImmutable());
        
        $this->entityManager->persist($vote);
        $message = 'Лайк додано';
    }

    $this->entityManager->flush();

    // Отримуємо актуальну кількість лайків та дизлайків
    $likesCount = $likeRepository->countLikesForBlogPost($blogPost->getId());
    $dislikesCount = $likeRepository->countDislikesForBlogPost($blogPost->getId());

    return $this->json([
        'success' => true,
        'likes' => $likesCount,
        'dislikes' => $dislikesCount,
        'message' => $message,
        'userVote' => 'like' // Додаємо інформацію про поточний голос
    ]);
}

/**
 * Дизлайк блогу (AJAX)
 */
#[Route('/{id}/dislike', name: 'blog_dislike', methods: ['POST'])]
public function dislike(BlogPost $blogPost, Request $request, LikeRepository $likeRepository): JsonResponse
{
    if (!$this->isCsrfTokenValid('dislike' . $blogPost->getId(), $request->request->get('_token'))) {
        return $this->json(['success' => false, 'error' => 'Invalid CSRF token'], 400);
    }

    $user = $this->getUser();
    if (!$user) {
        return $this->json(['success' => false, 'error' => 'Необхідно увійти в систему'], 401);
    }

    $existingVote = $likeRepository->findOneBy([
        'user' => $user,
        'blogPost' => $blogPost
    ]);

    $message = '';
    
    if ($existingVote) {
        if (!$existingVote->isLike()) { // Це дизлайк (isLike = false)
            $this->entityManager->remove($existingVote);
            $message = 'Дизлайк видалено';
        } else {
            // Змінюємо лайк на дизлайк
            $existingVote->setIsLike(false);
            $message = 'Лайк змінено на дизлайк';
        }
    } else {
        $vote = new \App\Entity\Like();
        $vote->setUser($user);
        $vote->setBlogPost($blogPost);
        $vote->setIsLike(false); // false = дизлайк
        $vote->setCreatedAt(new \DateTimeImmutable());
        
        $this->entityManager->persist($vote);
        $message = 'Дизлайк додано';
    }

    $this->entityManager->flush();

    $likesCount = $likeRepository->countLikesForBlogPost($blogPost->getId());
    $dislikesCount = $likeRepository->countDislikesForBlogPost($blogPost->getId());

    return $this->json([
        'success' => true,
        'likes' => $likesCount,
        'dislikes' => $dislikesCount,
        'message' => $message,
        'userVote' => 'dislike'
    ]);
  }

    /**
     * Зберегти в закладки (AJAX)
     */
    #[Route('/{id}/bookmark', name: 'blog_bookmark', methods: ['POST'])]
    public function bookmark(BlogPost $blogPost, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('bookmark' . $blogPost->getId(), $request->request->get('_token'))) {
            return $this->json(['error' => 'Invalid CSRF token'], 400);
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Необхідно увійти в систему'], 401);
        }

        // Перевіряємо чи вже в закладках
        $existingBookmark = $blogPost->getBookmarks()->filter(
            fn($bookmark) => $bookmark->getUser() === $user
        )->first();

        if ($existingBookmark) {
            // Видалити з закладок
            $this->entityManager->remove($existingBookmark);
            $message = 'Видалено з закладок';
            $isBookmarked = false;
        } else {
            // Додати в закладки
            $bookmark = new \App\Entity\BlogBookmark();
            $bookmark->setUser($user);
            $bookmark->setBlogPost($blogPost);
            $bookmark->setCreatedAt(new \DateTimeImmutable());
            
            $this->entityManager->persist($bookmark);
            $message = 'Додано в закладки';
            $isBookmarked = true;
        }

        $this->entityManager->flush();

        return $this->json([
            'isBookmarked' => $isBookmarked,
            'message' => $message,
        ]);
    }

    /**
     * Поділитися блогом
     */
    #[Route('/{id}/share', name: 'blog_share', methods: ['POST'])]
    public function share(BlogPost $blogPost, Request $request): JsonResponse
    {
        $platform = $request->request->get('platform');
        $validPlatforms = ['facebook', 'twitter', 'linkedin', 'telegram', 'viber', 'whatsapp'];

        if (!in_array($platform, $validPlatforms)) {
            return $this->json(['error' => 'Невірна платформа'], 400);
        }

        $user = $this->getUser();
        
        $share = new \App\Entity\BlogShare();
        $share->setBlogPost($blogPost);
        $share->setPlatform($platform);
        $share->setSharedAt(new \DateTimeImmutable());
        
        if ($user) {
            $share->setUser($user);
        }

        $this->entityManager->persist($share);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Поділилися успішно',
            'shareCount' => $blogPost->getShares()->count() + 1,
        ]);
    }

    #[Route('/author/{id}', name: 'blog_author')]
    public function authorPosts(User $author, BlogRepository $blogRepository): Response
    {
    $posts = $blogRepository->findBy([
        'author' => $author,
        'status' => 'published' // або ваш статус опублікованих постів
    ], ['publishedAt' => 'DESC']);
    
    return $this->render('blog/author.html.twig', [
        'author' => $author,
        'posts' => $posts
    ]);
    }
    /**
     * RSS стрічка блогів
     */
    #[Route('/rss', name: 'blog_rss')]
    public function rss(): Response
    {
        $posts = $this->blogPostRepository->findBy(
            ['status' => BlogPost::STATUS_PUBLISHED],
            ['publishedAt' => 'DESC'],
            20
        );

        $response = $this->render('blog/rss.xml.twig', [
            'posts' => $posts,
            'lastBuildDate' => new \DateTimeImmutable(),
        ]);

        $response->headers->set('Content-Type', 'application/rss+xml');

        return $response;
    }

    /**
     * Карта сайту для блогів
     */
    #[Route('/sitemap', name: 'blog_sitemap')]
    public function sitemap(): Response
    {
        $posts = $this->blogPostRepository->findBy(
            ['status' => BlogPost::STATUS_PUBLISHED],
            ['publishedAt' => 'DESC']
        );

        $categories = $this->blogCategoryRepository->findAll();
        $tags = $this->TagRepository->findAll();

        $response = $this->render('blog/sitemap.xml.twig', [
            'posts' => $posts,
            'categories' => $categories,
            'tags' => $tags,
        ]);

        $response->headers->set('Content-Type', 'application/xml');

        return $response;
    }

    /**
     * Популярні блоги (AJAX)
     */
    #[Route('/popular/json', name: 'blog_popular_json')]
    public function popularJson(): JsonResponse
    {
        $posts = $this->blogPostRepository->findPopular(10);
        
        $data = array_map(function($post) {
            return [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'slug' => $post->getSlug(),
                'excerpt' => $post->getExcerpt(),
                'views' => $post->getViewCount(),
                'likes' => $post->getLikeCount(),
                'readingTime' => $post->getReadingTime(),
                'publishedAt' => $post->getPublishedAt()->format('d.m.Y'),
                'author' => $post->getAuthor()->getUsername(),
            ];
        }, $posts);

        return $this->json($data);
    }

    /**
     * Останні коментарі (AJAX)
     */
    #[Route('/comments/latest', name: 'blog_comments_latest')]
    public function latestComments(): JsonResponse
    {
        $comments = $this->entityManager->getRepository(BlogComment::class)
            ->findBy(['isApproved' => true], ['createdAt' => 'DESC'], 10);

        $data = array_map(function($comment) {
            return [
                'id' => $comment->getId(),
                'content' => substr($comment->getContent(), 0, 100) . '...',
                'author' => $comment->getUser()->getUsername(),
                'postTitle' => $comment->getBlogPost()->getTitle(),
                'postSlug' => $comment->getBlogPost()->getSlug(),
                'createdAt' => $comment->getCreatedAt()->format('d.m.Y H:i'),
            ];
        }, $comments);

        return $this->json($data);
    }
}