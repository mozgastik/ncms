<?php

namespace App\Controller\Admin;

use App\Entity\Blog\BlogCategory;
use App\Form\BlogCategoryType;
use App\Repository\BlogCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/blog/categories')]
class BlogCategoryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BlogCategoryRepository $categoryRepository,
        private SluggerInterface $slugger
    ) {}

    #[Route('/', name: 'admin_blog_category_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        // Сортуємо за sortOrder та id
        $queryBuilder = $this->categoryRepository->createQueryBuilder('c')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.id', 'DESC');

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            15
        );

        return $this->render('admin/blog/category.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/new', name: 'admin_blog_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $category = new BlogCategory();
        
        // Встановлюємо значення за замовчуванням
        $category->setIsActive(true);
        $category->setSortOrder(0);
        
        $form = $this->createForm(BlogCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Генеруємо slug з назви
            $slug = $this->slugger->slug($category->getName())->lower()->toString();
            $category->setSlug($slug);

            $this->entityManager->persist($category);
            $this->entityManager->flush();

            $this->addFlash('success', 'Категорію успішно створено!');
            return $this->redirectToRoute('admin_blog_category_index');
        }

        // Отримуємо всі категорії для випадаючого списку батьківських категорій
        $categories = $this->categoryRepository->findBy([], ['sortOrder' => 'ASC']);

        return $this->render('admin/blog/new_category.html.twig', [
            'form' => $form->createView(),
            'categories' => $categories,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_blog_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, BlogCategory $category): Response
    {
        $form = $this->createForm(BlogCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Оновлюємо slug якщо назва змінилася
            $slug = $this->slugger->slug($category->getName())->lower()->toString();
            $category->setSlug($slug);

            $this->entityManager->flush();

            $this->addFlash('success', 'Категорію успішно оновлено!');
            return $this->redirectToRoute('admin_blog_category_index');
        }

        // Отримуємо всі категорії для випадаючого списку батьківських категорій (крім поточної)
        $categories = $this->categoryRepository->createQueryBuilder('c')
            ->where('c.id != :id')
            ->setParameter('id', $category->getId())
            ->orderBy('c.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/blog/edit_category.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
            'categories' => $categories,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_blog_category_delete', methods: ['POST'])]
    public function delete(Request $request, BlogCategory $category): Response
    {
        if ($this->isCsrfTokenValid('delete' . $category->getId(), $request->request->get('_token'))) {
            
            // Перевіряємо чи є дочірні категорії
            if ($category->getBlogCategories()->count() > 0) {
                $this->addFlash('error', 'Неможливо видалити категорію, яка має підкатегорії!');
                return $this->redirectToRoute('admin_blog_category_index');
            }

            // Перевіряємо чи є блоги в цій категорії (якщо є зв'язок)
            // Цей код потрібно буде додати, коли буде зв'язок з BlogPost

            $this->entityManager->remove($category);
            $this->entityManager->flush();

            $this->addFlash('success', 'Категорію успішно видалено!');
        }

        return $this->redirectToRoute('admin_blog_category_index');
    }

    #[Route('/{id}/toggle', name: 'admin_blog_category_toggle', methods: ['POST'])]
    public function toggle(BlogCategory $category): JsonResponse
    {
        $category->setIsActive(!$category->isActive());
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'isActive' => $category->isActive()
        ]);
    }

    #[Route('/bulk-delete', name: 'admin_blog_category_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('bulk-delete', $request->request->get('_token'))) {
            $this->addFlash('error', 'Недійсний CSRF токен');
            return $this->redirectToRoute('admin_blog_category_index');
        }

        $ids = $request->request->all('ids') ?? [];
        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($ids as $id) {
            $category = $this->categoryRepository->find($id);
            if ($category) {
                // Перевіряємо чи є дочірні категорії
                if ($category->getBlogCategories()->count() === 0) {
                    $this->entityManager->remove($category);
                    $deletedCount++;
                } else {
                    $skippedCount++;
                }
            }
        }

        $this->entityManager->flush();

        if ($deletedCount > 0) {
            $this->addFlash('success', "Видалено {$deletedCount} категорій");
        }
        if ($skippedCount > 0) {
            $this->addFlash('warning', "Пропущено {$skippedCount} категорій (мають підкатегорії)");
        }

        return $this->redirectToRoute('admin_blog_category_index');
    }
}