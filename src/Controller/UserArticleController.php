<?php
// src/Controller/UserArticleController.php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Image;
use App\Form\UserArticleType;
use App\Repository\ArticleRepository;
use App\Service\ImageService;
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

#[Route('/user/articles')]
#[IsGranted('ROLE_USER')]
class UserArticleController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'html_sanitizer.sanitizer.article_content')]
        private readonly HtmlSanitizerInterface $htmlSanitizer,
        private readonly SluggerInterface $slugger,
        private readonly ImageService $imageService // ← Додаємо ImageService
    ) {}

    #[Route('/', name: 'user_articles', methods: ['GET'])]
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

    #[Route('/new', name: 'user_article_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $article = new Article();
        $article->setAuthor($this->getUser());
        $article->setStatus(Article::STATUS_PENDING);
        
        $form = $this->createForm(UserArticleType::class, $article);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$article->getSlug()) {
                $article->setSlug($this->generateSlug($article->getTitle()));
            }
            
            // Очищаємо HTML контент перед збереженням
            $cleanContent = $this->htmlSanitizer->sanitize($article->getContent());
            $article->setContent($cleanContent);
            
            if ($article->getExcerpt()) {
                $cleanExcerpt = $this->htmlSanitizer->sanitize($article->getExcerpt());
                $article->setExcerpt($cleanExcerpt);
            }
            
            $article->setReadingTime($this->calculateReadingTime($article->getContent()));
            
            $this->entityManager->persist($article);
            $this->entityManager->flush();

            // ============================================
            // ЗБЕРІГАЄМО ЗОБРАЖЕННЯ
            // ============================================
            $imageIds = $request->request->get('image_ids', '[]');
            
            if (!empty($imageIds) && $imageIds !== '[]') {
                $imageIdsArray = json_decode($imageIds, true);
                
                if (is_array($imageIdsArray) && !empty($imageIdsArray)) {
                    foreach ($imageIdsArray as $imageId) {
                        $image = $this->entityManager->getRepository(Image::class)->find($imageId);
                        if ($image && !$image->getArticle()) {
                            $image->setArticle($article);
                            $this->entityManager->persist($image);
                        }
                    }
                    $this->entityManager->flush();
                }
            }
            
            $this->addFlash('success', 'Статтю успішно відправлено на модерацію!');
            return $this->redirectToRoute('user_articles');
        }
        
        return $this->render('user/article/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/user/article/edit/{id}', name: 'user_article_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Article $article): Response
    {
        if ($article->getAuthor() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Ця стаття не належить вам');
        }
        
        if ($article->getStatus() !== Article::STATUS_DRAFT && $article->getStatus() !== Article::STATUS_REJECTED) {
            $this->addFlash('error', 'Цю статтю вже не можна редагувати');
            return $this->redirectToRoute('user_articles');
        }
        
        $form = $this->createForm(UserArticleType::class, $article);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $article->setReadingTime($this->calculateReadingTime($article->getContent()));
            $article->setStatus(Article::STATUS_PENDING);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Статтю оновлено та відправлено на модерацію!');
            return $this->redirectToRoute('user_articles');
        }
        
        return $this->render('user/article/edit.html.twig', [
            'form' => $form->createView(),
            'article' => $article,
        ]);
    }

    #[Route('/delete/{id}', name: 'user_article_delete', methods: ['POST'])]
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

    #[Route('/{id}', name: 'user_article_show', methods: ['GET'])]
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