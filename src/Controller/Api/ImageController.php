<?php

namespace App\Controller\Api;

use App\Entity\Article\ArticleImage;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ImageController extends AbstractController
{
    #[Route('/api/images/upload', name: 'api_image_upload', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $entityManager): JsonResponse
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
            
            $entityManager->persist($image);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'id' => $image->getId(),
                'url' => $image->getImageUrl(),
                'path' => $image->getImageName(),
                'name' => $file->getClientOriginalName(),
                'message' => 'Зображення завантажено'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Помилка: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/images/list', name: 'api_images_list', methods: ['GET'])]
    public function list(EntityManagerInterface $entityManager): JsonResponse
    {
        $images = $entityManager
            ->getRepository(ArticleImage::class)
            ->findBy([], ['uploadedAt' => 'DESC']);

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

    #[Route('/api/images/delete/{id}', name: 'api_image_delete', methods: ['DELETE'])]
    public function delete(ArticleImage $image, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $entityManager->remove($image);
            $entityManager->flush();
            
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
}