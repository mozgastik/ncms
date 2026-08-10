<?php
// src/Service/ArticleService.php

namespace App\Service;

use App\Entity\Article\Article;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ArticleService
{
    public function __construct(
        #[Autowire(service: 'html_sanitizer.sanitizer.article_content')]
        private HtmlSanitizerInterface $htmlSanitizer,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger
    ) {}

    public function createArticle(Article $article, User $author): Article
    {
        $article->setAuthor($author);
        $article->setStatus(Article::STATUS_PENDING);
        $article->setCreatedAt(new \DateTime());
        
        if (!$article->getSlug()) {
            $article->setSlug($this->generateSlug($article->getTitle()));
        }
        
        $article->calculateReadingTime();
        
        $this->entityManager->persist($article);
        $this->entityManager->flush();
        
        return $article;
    }

    public function updateArticle(Article $article, ?User $moderator = null): Article
    {
        $article->setUpdatedAt(new \DateTime());
        $article->calculateReadingTime();
        
        if ($moderator) {
            $article->setModerator($moderator);
        }
        
        $this->entityManager->flush();
        
        return $article;
    }

    public function approveArticle(Article $article, User $moderator, ?string $notes = null): Article
    {
        $article->approve($moderator);
        $article->setModeratorNotes($notes);
        $article->setUpdatedAt(new \DateTime());
        
        $this->entityManager->flush();
        
        return $article;
    }

    public function rejectArticle(Article $article, User $moderator, string $reason): Article
    {
        $article->rejectWithReason($reason, $moderator);
        $article->setUpdatedAt(new \DateTime());
        
        $this->entityManager->flush();
        
        return $article;
    }

    public function publishArticle(Article $article, User $moderator): Article
    {
        $article->publishWithModerator($moderator);
        $article->setUpdatedAt(new \DateTime());
        
        $this->entityManager->flush();
        
        return $article;
    }

    public function deleteArticle(Article $article): void
    {
        $this->entityManager->remove($article);
        $this->entityManager->flush();
    }

    private function generateSlug(string $title): string
    {
        $slug = $this->slugger->slug($title)->lower();
        
        // Перевірка унікальності
        $existing = $this->entityManager->getRepository(Article::class)
            ->findOneBy(['slug' => $slug]);
        
        if ($existing) {
            $slug = $slug . '-' . uniqid();
        }
        
        return $slug;
    }

    public function calculateReadingTime(string $content): int
    {
        $wordCount = str_word_count(strip_tags($content));
        return max(1, (int) ceil($wordCount / 200));
    }

    public function sanitizeContent(string $dirtyHtml): string
    {
        return $this->htmlSanitizer->sanitize($dirtyHtml);
    }

}