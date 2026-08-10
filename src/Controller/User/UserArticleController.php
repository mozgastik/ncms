<?php
// src/Controller/User/UserArticleController.php

namespace App\Controller\User;

use App\Entity\Article\Article;
use App\Entity\Article\ArticleImage;
use App\Form\UserArticleType;
use App\Repository\ArticleRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/user')]
#[IsGranted('ROLE_USER')]
class UserArticleController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'html_sanitizer.sanitizer.article_content')]
        private readonly HtmlSanitizerInterface $htmlSanitizer,
        private readonly SluggerInterface $slugger,
    ) {}

    #[Route('/articles', name: 'user_articles', methods: ['GET'])]
    public function myArticles(ArticleRepository $repository): Response
    {
        $user = $this->getUser();
        
        $articles = $repository->findBy(['author' => $user], ['createdAt' => 'DESC']);
        
        $stats = [
            'total' => count($articles),
            'draft' => count(array_filter($articles, fn($a) => $a->getStatus() === Article::STATUS_DRAFT)),
            'pending' => count(array_filter($articles, fn($a) => $a->getStatus() === Article::STATUS_PENDING)),
            'approved' => count(array_filter($articles, fn($a) => $a->getStatus() === Article::STATUS_APPROVED)),
            'published' => count(array_filter($articles, fn($a) => $a->getStatus() === Article::STATUS_PUBLISHED)),
            'rejected' => count(array_filter($articles, fn($a) => $a->getStatus() === Article::STATUS_REJECTED)),
            'archived' => count(array_filter($articles, fn($a) => $a->getStatus() === Article::STATUS_ARCHIVED)),
        ];
        
        return $this->render('user/article/index.html.twig', [
            'articles' => $articles,
            'stats' => $stats,
        ]);
    }

    #[Route('/article/new', name: 'user_article_new', methods: ['GET', 'POST'])]
public function new(Request $request, TagRepository $tagRepository): Response
{
    $article = new Article();
    $article->setAuthor($this->getUser());
    $article->setStatus(Article::STATUS_PENDING);
    
    $form = $this->createForm(UserArticleType::class, $article);
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
        // Генерація slug
        if (!$article->getSlug()) {
            $article->setSlug($this->generateSlug($article->getTitle()));
        }
        
        // Очищаємо HTML контент
        $cleanContent = $this->htmlSanitizer->sanitize($article->getContent());
        $article->setContent($cleanContent);
        
        if ($article->getExcerpt()) {
            $cleanExcerpt = $this->htmlSanitizer->sanitize($article->getExcerpt());
            $article->setExcerpt($cleanExcerpt);
        }
        
        $article->setReadingTime($this->calculateReadingTime($article->getContent()));
        
        // ============================================
        // ЗБЕРІГАЄМО ОБКЛАДИНКУ
        // ============================================
        
        // 1. Зберігаємо завантажений файл через VichUploader
        $coverImageFile = $form->get('coverImageFile')->getData();
        if ($coverImageFile) {
            $article->setCoverImageFile($coverImageFile);
        }
        
        // 2. Зберігаємо URL зображення (якщо додано через поле в шаблоні)
        $coverImageUrl = $request->request->get('cover_image_url');
        if ($coverImageUrl && filter_var($coverImageUrl, FILTER_VALIDATE_URL)) {
            // Зберігаємо URL в окреме поле, або в coverImage як URL
            // Якщо у вас немає окремого поля для URL, ви можете зберегти його як coverImage
            // або додати нове поле coverImageUrl в сутність
            $article->setCoverImage($coverImageUrl);
        }
        
        $this->entityManager->persist($article);
        $this->entityManager->flush();
        
        $this->addFlash('success', 'Статтю успішно відправлено на модерацію!');
        return $this->redirectToRoute('user_articles');
    }
    
    $allTags = $tagRepository->findAll();
    
    return $this->render('user/article/new.html.twig', [
        'form' => $form->createView(),
        'tags' => $allTags,
    ]);
}

    #[Route('/article/{id}/edit', name: 'user_article_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Article $article, TagRepository $tagRepository): Response
    {
        // Перевірка прав
        if ($article->getAuthor() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Ця стаття не належить вам');
        }
        
        // Перевірка статусу
        if (!in_array($article->getStatus(), ['draft', 'rejected'])) {
            $this->addFlash('warning', 'Цю статтю вже не можна редагувати');
            return $this->redirectToRoute('user_articles');
        }
        
        $form = $this->createForm(UserArticleType::class, $article);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Оновлюємо обкладинку
            $coverImageFile = $form->get('coverImageFile')->getData();
            if ($coverImageFile) {
                $article->setCoverImageFile($coverImageFile);
            }
            
            // Очищаємо HTML контент
            $cleanContent = $this->htmlSanitizer->sanitize($article->getContent());
            $article->setContent($cleanContent);
            
            if ($article->getExcerpt()) {
                $cleanExcerpt = $this->htmlSanitizer->sanitize($article->getExcerpt());
                $article->setExcerpt($cleanExcerpt);
            }
            
            $article->setReadingTime($this->calculateReadingTime($article->getContent()));
            $article->setUpdatedAt(new \DateTime());
            $article->setStatus(Article::STATUS_PENDING);
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Статтю оновлено та відправлено на модерацію!');
            return $this->redirectToRoute('user_articles');
        }
        
        // Отримуємо всі теги
        $allTags = $tagRepository->findAll();
        
        return $this->render('user/article/edit.html.twig', [
            'form' => $form->createView(),
            'article' => $article,
            'tags' => $allTags,
        ]);
    }

    #[Route('/article/{id}/delete', name: 'user_article_delete', methods: ['POST'])]
    public function delete(Request $request, Article $article): Response
    {
        if ($article->getAuthor() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Ця стаття не належить вам');
        }
        
        if ($this->isCsrfTokenValid('delete' . $article->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($article);
            $this->entityManager->flush();
            $this->addFlash('success', 'Статтю видалено');
        }
        
        return $this->redirectToRoute('user_articles');
    }

    #[Route('/article/{id}', name: 'user_article_show', methods: ['GET'])]
    public function show(Article $article): Response
    {
        if ($article->getStatus() !== Article::STATUS_PUBLISHED && 
            $article->getAuthor() !== $this->getUser() && 
            !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException('Стаття не знайдена');
        }
        
        return $this->render('user/article/show.html.twig', [
            'article' => $article,
        ]);
    }

    #[Route('/article/{id}/submit', name: 'user_article_submit', methods: ['POST'])]
    public function submit(Request $request, Article $article): JsonResponse
    {
        if ($article->getAuthor() !== $this->getUser()) {
            return $this->json(['success' => false, 'message' => 'У вас немає прав'], 403);
        }
        
        if ($article->getStatus() !== Article::STATUS_DRAFT) {
            return $this->json(['success' => false, 'message' => 'Стаття вже відправлена'], 400);
        }
        
        if (!$this->isCsrfTokenValid('submit' . $article->getId(), $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Невірний CSRF токен'], 400);
        }
        
        $article->setStatus(Article::STATUS_PENDING);
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Статтю відправлено на модерацію'
        ]);
    }

    // ============================================
    // API МЕТОДИ ДЛЯ РОБОТИ З ЗОБРАЖЕННЯМИ
    // ============================================

    #[Route('/image/upload', name: 'user_article_image_upload', methods: ['POST'])]
    public function uploadImage(Request $request): JsonResponse
    {
        $file = $request->files->get('image');
        
        if (!$file) {
            return $this->json([
                'success' => false,
                'message' => 'Файл не знайдено'
            ], 400);
        }

        try {
            $image = new ArticleImage();
            $image->setImageFile($file);
            $image->setAlt($file->getClientOriginalName());
            
            $this->entityManager->persist($image);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'id' => $image->getId(),
                'url' => $image->getImageUrl(),
                'path' => $image->getImageName(),
                'message' => 'Зображення завантажено'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Помилка: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/image/delete/{id}', name: 'user_article_image_delete', methods: ['DELETE'])]
    public function deleteImage(ArticleImage $image): JsonResponse
    {
        // Перевіряємо чи зображення прив'язане до статті користувача
        if ($image->getArticle() && $image->getArticle()->getAuthor() !== $this->getUser()) {
            return $this->json([
                'success' => false,
                'message' => 'У вас немає прав на видалення цього зображення'
            ], 403);
        }

        try {
            $this->entityManager->remove($image);
            $this->entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Зображення видалено'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Помилка: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/image/list', name: 'user_article_image_list', methods: ['GET'])]
    public function listImages(): JsonResponse
    {
        $user = $this->getUser();
        
        // Отримуємо всі зображення, які не прив'язані до статей
        $images = $this->entityManager
            ->getRepository(ArticleImage::class)
            ->createQueryBuilder('i')
            ->where('i.article IS NULL')
            ->orWhere('i.article IN (:articles)')
            ->setParameter('articles', $user->getArticles())
            ->orderBy('i.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        $data = array_map(function($image) {
            return [
                'id' => $image->getId(),
                'url' => $image->getImageUrl(),
                'name' => $image->getImageName(),
                'alt' => $image->getAlt(),
                'uploadedAt' => $image->getUploadedAt()->format('c'),
            ];
        }, $images);
        
        return $this->json([
            'success' => true,
            'images' => $data,
        ]);
    }

    // В UserArticleController додайте цей метод, якщо його немає
#[Route('/preview', name: 'user_article_preview', methods: ['POST'])]
public function preview(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    
    $title = $data['title'] ?? 'Без заголовку';
    $content = $data['content'] ?? '';
    $excerpt = $data['excerpt'] ?? '';
    $coverImage = $data['coverImage'] ?? '';
    
    return $this->json([
        'success' => true,
        'html' => $this->renderView('user/article/_preview.html.twig', [
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'coverImage' => $coverImage,
        ])
    ]);
}

    // ============================================
    // ДОПОМІЖНІ МЕТОДИ
    // ============================================

    private function generateSlug(string $title): string
    {
        $slug = $this->slugger->slug($title)->lower();
        
        $existing = $this->entityManager->getRepository(Article::class)->findOneBy(['slug' => $slug]);
        if ($existing) {
            $slug = $slug . '-' . uniqid();
        }
        
        return $slug;
    }
    
    private function calculateReadingTime(?string $content): int
    {
        if (!$content) {
            return 1;
        }
        
        $text = strip_tags($content);
        $wordCount = str_word_count($text, 0, 'АБВГҐДЕЄЖЗИІЇЙКЛМНОПРСТУФХЦЧШЩЬЮЯабвгґдеєжзиіїйклмнопрстуфхцчшщьюя');
        $minutes = ceil($wordCount / 200);
        
        return max(1, $minutes);
    }
}