<?php
// src/Controller/TagController.php

namespace App\Controller\Front;

use App\Entity\Article\Article;
use App\Entity\Admin\Tag;
use App\Repository\TagRepository;
use App\Repository\ArticleRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TagController extends AbstractController
{
    #[Route('/tag/{slug}', name: 'app_tag_show')]
    public function show(
        string $slug,
        TagRepository $tagRepository,
        ArticleRepository $articleRepository,
        PaginatorInterface $paginator,
        Request $request
    ): Response
    {
        // Знаходимо тег
        $tag = $tagRepository->findOneBy(['slug' => $slug]);
        
        if (!$tag) {
            throw $this->createNotFoundException('Тег не знайдено');
        }

        // Отримуємо статті з цим тегом
        $query = $articleRepository->createQueryBuilder('a')
            ->innerJoin('a.tags', 't')
            ->andWhere('t.id = :tagId')
            ->andWhere('a.status = :status')
            ->setParameter('tagId', $tag->getId())
            ->setParameter('status', Article::STATUS_PUBLISHED)
            ->orderBy('a.publishedAt', 'DESC')
            ->getQuery();

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            12
        );

        return $this->render('tag/show.html.twig', [
            'tag' => $tag,
            'pagination' => $pagination,
        ]);
    }
    
    #[Route('/tags', name: 'app_tag_index')]
    public function index(TagRepository $tagRepository): Response
    {
        $tags = $tagRepository->findAll();
        
        return $this->render('tag/index.html.twig', [
            'tags' => $tags,
        ]);
    }
}