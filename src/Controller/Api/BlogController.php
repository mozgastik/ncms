<?php
// src/Controller/Api/BlogController.php

namespace App\Controller\Api;

use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/blog')]
class BlogController extends AbstractController
{
    #[Route('/tags', name: 'blog_tags_json')]
    public function tags(TagRepository $tagRepository): JsonResponse
    {
        $tags = $tagRepository->findAll();
        
        $data = array_map(function($tag) {
            return [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
                'slug' => $tag->getSlug(),
                'usageCount' => $tag->getUsageCount(),
            ];
        }, $tags);
        
        return $this->json($data);
    }
}