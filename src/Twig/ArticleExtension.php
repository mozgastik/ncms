<?php

namespace App\Twig;

use App\Entity\Article\Article;
use App\Entity\System\Image;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ArticleExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_article_cover', [$this, 'getArticleCover']),
            new TwigFunction('get_article_images', [$this, 'getArticleImages']),
        ];
    }

    public function getArticleCover(Article $article): ?Image
    {
        return $article->getFeaturedImage();
    }

    public function getArticleImages(Article $article): array
    {
        return $article->getImages()->toArray();
    }
}