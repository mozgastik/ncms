<?php
// src/Controller/PageController.php

namespace App\Controller\Front;

use App\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PageController extends AbstractController
{
    #[Route('/page/{slug}', name: 'app_page_show')]
    public function show(string $slug, PageRepository $repo): Response
    {
        $page = $repo->findPublishedBySlug($slug);
        if (!$page) {
            throw $this->createNotFoundException('Сторінку не знайдено');
        }
        return $this->render('components/page/show.html.twig', [
            'page' => $page,
        ]);
    }
}