<?php
// src/Controller/Admin/CategoryController.php

namespace App\Controller\Admin;

use App\Entity\Admin\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\CategoryManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder; // ← Додайте цей use
use Doctrine\ORM\Tools\Pagination\Paginator; // ← Додайте цей use
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/categories')]
#[IsGranted('ROLE_ADMIN')]
class CategoryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CategoryManager $categoryManager
    ) {}

    #[Route('/', name: 'admin_category_index', methods: ['GET'])]
    public function index(Request $request, CategoryRepository $repository): Response
    {
        $filters = [
            'search' => $request->query->get('search'),
            'type' => $request->query->get('type'),
            'active' => $request->query->get('active'),
        ];

        $queryBuilder = $repository->getAdminList($filters);
        
        $pagination = $this->paginate($queryBuilder, $request->query->getInt('page', 1), 20);

        return $this->render('admin/category/index.html.twig', [
            'categories' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $filters,
        ]);
    }

    #[Route('/new', name: 'admin_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $category = new Category();
        $category->setCreatedBy($this->getUser());

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->categoryManager->createCategory($category);
            
            $this->addFlash('success', 'Категорію успішно створено');
            return $this->redirectToRoute('admin_category_index');
        }

        return $this->render('admin/category/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category): Response
    {
        $category->setUpdatedBy($this->getUser());

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->categoryManager->updateCategory($category);
            
            $this->addFlash('success', 'Категорію оновлено');
            return $this->redirectToRoute('admin_category_index');
        }

        return $this->render('admin/category/edit.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_category_delete', methods: ['POST'])]
    public function delete(Request $request, Category $category): Response
    {
        if ($this->isCsrfTokenValid('delete' . $category->getId(), $request->request->get('_token'))) {
            try {
                $this->categoryManager->deleteCategory($category);
                $this->addFlash('success', 'Категорію видалено');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Неможливо видалити категорію, яка містить елементи');
            }
        }

        return $this->redirectToRoute('admin_category_index');
    }

    #[Route('/{id}/toggle', name: 'admin_category_toggle', methods: ['POST'])]
    public function toggle(Category $category): Response
    {
        $category->setIsActive(!$category->isActive());
        $this->entityManager->flush();
        $this->categoryManager->clearCache();

        return $this->json([
            'success' => true,
            'active' => $category->isActive(),
        ]);
    }

    #[Route('/tree', name: 'admin_category_tree', methods: ['GET'])]
    public function tree(CategoryRepository $repository): Response
    {
        $tree = $this->categoryManager->getTree();

        return $this->render('admin/category/tree.html.twig', [
            'tree' => $tree,
        ]);
    }

    #[Route('/{id}/move-up', name: 'admin_category_move_up', methods: ['POST'])]
    public function moveUp(Category $category): Response
    {
        // Логіка переміщення вгору
        return $this->redirectToRoute('admin_category_index');
    }

    #[Route('/{id}/move-down', name: 'admin_category_move_down', methods: ['POST'])]
    public function moveDown(Category $category): Response
    {
        // Логіка переміщення вниз
        return $this->redirectToRoute('admin_category_index');
    }

    private function paginate(QueryBuilder $queryBuilder, int $page, int $limit): array
    {
        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($queryBuilder);
        
        $totalItems = count($paginator);
        $pagesCount = ceil($totalItems / $limit);
        
        $paginator
            ->getQuery()
            ->setFirstResult($limit * ($page - 1))
            ->setMaxResults($limit);

        return [
            'items' => iterator_to_array($paginator),
            'current' => $page,
            'pages' => $pagesCount,
            'total' => $totalItems,
            'limit' => $limit,
        ];
    }
}