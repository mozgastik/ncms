<?php

namespace App\Controller\Front;

use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search')]
    public function index(Request $request, ArticleRepository $articleRepository): Response
    {
        $query = $request->query->get('q', '');
        $articles = [];

        if (!empty($query)) {
            $articles = $articleRepository->search($query);
        }

        return $this->render('search/index.html.twig', [
            'query' => $query,
            'articles' => $articles,
            'results_count' => count($articles),
        ]);
    }
}