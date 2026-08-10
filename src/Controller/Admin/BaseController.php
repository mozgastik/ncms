<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BaseController extends AbstractController
{
    
    
    #[Route('/admin/quotes', name: 'admin_quote_index')]
    public function quotes(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('admin/base_section.html.twig', [
            'title' => 'Управління цитатами',
        ]);
    }
    
    #[Route('/admin/slider', name: 'admin_slider_index')]
    public function slider(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('admin/base_section.html.twig', [
            'title' => 'Управління слайдером',
        ]);
    }
    
    #[Route('/admin/gallery', name: 'admin_gallery_index')]
    public function gallery(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('admin/base_section.html.twig', [
            'title' => 'Управління галереєю',
        ]);
    }
   // #[Route('/admin/settings', name: 'admin_settings_index')]
   // public function settings(): Response
    //{
       // $this->denyAccessUnlessGranted('ROLE_ADMIN');
        //return $this->render('admin/base_section.html.twig', [
            //'title' => 'Налаштування',
       // ]);
   // }
    
    #[Route('/admin/stats', name: 'admin_stats')]
    public function stats(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('admin/base_section.html.twig', [
            'title' => 'Статистика',
        ]);
    }
}