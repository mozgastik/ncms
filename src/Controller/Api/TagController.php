<?php

namespace App\Controller\Api;

use App\Entity\Tag;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/tags')]
class TagController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private TagRepository $tagRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        TagRepository $tagRepository
    ) {
        $this->entityManager = $entityManager;
        $this->tagRepository = $tagRepository;
    }

    /**
     * Отримати список всіх тегів з кількістю статей
     */
    #[Route('/list', name: 'api_tags_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // Отримуємо всі теги з кількістю
        $tags = $this->tagRepository->findAllWithCount();
        
        // Отримуємо популярні теги (опціонально)
        $limit = $request->query->getInt('limit', 0);
        $popularTags = $this->tagRepository->findPopularTags($limit ?: 20);
        
        // Форматуємо відповідь
        $data = array_map(function($tag) {
            return [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
                'slug' => $tag->getSlug(),
                'color' => $tag->getColor() ?? '#6b7280',
                'description' => $tag->getDescription(),
                'count' => $tag->getArticleCount(),
                'createdAt' => $tag->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $tags);
        
        return $this->json([
            'success' => true,
            'tags' => $data,
            'total' => count($data),
            'popular' => array_map(function($tag) {
                return [
                    'id' => $tag->getId(),
                    'name' => $tag->getName(),
                    'slug' => $tag->getSlug(),
                    'color' => $tag->getColor() ?? '#6b7280',
                    'count' => $tag->getArticleCount(),
                ];
            }, $popularTags),
        ]);
    }

    /**
     * Пошук тегів за назвою
     */
    #[Route('/search', name: 'api_tags_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = $request->query->getInt('limit', 10);
        
        if (strlen($query) < 2) {
            return $this->json([
                'success' => true,
                'tags' => [],
                'message' => 'Мінімум 2 символи для пошуку'
            ]);
        }
        
        $tags = $this->tagRepository->searchByName($query, $limit);
        
        $data = array_map(function($tag) {
            return [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
                'slug' => $tag->getSlug(),
                'color' => $tag->getColor() ?? '#6b7280',
                'count' => $tag->getArticleCount(),
            ];
        }, $tags);
        
        return $this->json([
            'success' => true,
            'tags' => $data,
            'query' => $query,
            'total' => count($data),
        ]);
    }

    /**
     * Створення нового тегу (для адміністраторів)
     */
    #[Route('/create', name: 'api_tags_create', methods: ['POST'])]
    public function create(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['name']) || empty(trim($data['name']))) {
            return $this->json([
                'success' => false,
                'message' => 'Назва тегу обов\'язкова'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // Перевіряємо чи тег вже існує
        $existingTag = $this->tagRepository->findOneBy(['name' => $data['name']]);
        if ($existingTag) {
            return $this->json([
                'success' => false,
                'message' => 'Тег з такою назвою вже існує'
            ], Response::HTTP_CONFLICT);
        }
        
        $tag = new Tag();
        $tag->setName($data['name']);
        $tag->setSlug($this->generateSlug($data['name']));
        $tag->setColor($data['color'] ?? '#6b7280');
        $tag->setDescription($data['description'] ?? null);
        $tag->setCreatedAt(new \DateTimeImmutable());
        
        // Валідація
        $errors = $validator->validate($tag);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json([
                'success' => false,
                'message' => 'Помилка валідації',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Тег успішно створено',
            'tag' => [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
                'slug' => $tag->getSlug(),
                'color' => $tag->getColor(),
                'description' => $tag->getDescription(),
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Оновлення тегу (для адміністраторів)
     */
    #[Route('/{id}/update', name: 'api_tags_update', methods: ['PUT', 'PATCH'])]
    public function update(
        Tag $tag, 
        Request $request, 
        ValidatorInterface $validator
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $data = json_decode($request->getContent(), true);
        
        if (isset($data['name']) && !empty(trim($data['name']))) {
            // Перевіряємо чи нове ім'я не зайняте
            $existingTag = $this->tagRepository->findOneBy(['name' => $data['name']]);
            if ($existingTag && $existingTag->getId() !== $tag->getId()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Тег з такою назвою вже існує'
                ], Response::HTTP_CONFLICT);
            }
            
            $tag->setName($data['name']);
            $tag->setSlug($this->generateSlug($data['name']));
        }
        
        if (isset($data['color'])) {
            $tag->setColor($data['color']);
        }
        
        if (isset($data['description'])) {
            $tag->setDescription($data['description']);
        }
        
        $tag->setUpdatedAt(new \DateTimeImmutable());
        
        // Валідація
        $errors = $validator->validate($tag);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json([
                'success' => false,
                'message' => 'Помилка валідації',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Тег успішно оновлено',
            'tag' => [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
                'slug' => $tag->getSlug(),
                'color' => $tag->getColor(),
                'description' => $tag->getDescription(),
            ]
        ]);
    }

    /**
     * Видалення тегу (для адміністраторів)
     */
    #[Route('/{id}/delete', name: 'api_tags_delete', methods: ['DELETE'])]
    public function delete(Tag $tag): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Перевіряємо чи є статті з цим тегом
        if ($tag->getArticles()->count() > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Неможливо видалити тег, який використовується в статтях'
            ], Response::HTTP_CONFLICT);
        }
        
        $this->entityManager->remove($tag);
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Тег успішно видалено'
        ]);
    }

    /**
     * Допоміжний метод для генерації slug
     */
    private function generateSlug(string $name): string
    {
        $translit = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'h','ґ'=>'g','д'=>'d','е'=>'e','є'=>'ye',
            'ж'=>'zh','з'=>'z','и'=>'y','і'=>'i','ї'=>'yi','й'=>'y','к'=>'k','л'=>'l',
            'м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
            'ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ю'=>'yu','я'=>'ya'
        ];
        
        $slug = strtolower($name);
        $slug = strtr($slug, $translit);
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        return $slug;
    }
}