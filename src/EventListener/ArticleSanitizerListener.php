<?php

namespace App\EventListener;

use App\Entity\Article\Article;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsEntityListener(event: Events::prePersist, method: 'sanitize', entity: Article::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'sanitize', entity: Article::class)]
class ArticleSanitizerListener
{
    public function __construct(
        #[Autowire(service: 'html_sanitizer.sanitizer.article_content')]
        private HtmlSanitizerInterface $htmlSanitizer,
    ) {}

    public function sanitize(Article $article): void
    {
        $article->setContent($this->htmlSanitizer->sanitize($article->getContent()));
        if ($article->getExcerpt()) {
            $article->setExcerpt($this->htmlSanitizer->sanitize($article->getExcerpt()));
        }
    }
}