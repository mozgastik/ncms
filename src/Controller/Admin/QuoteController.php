<?php
// src/Controller/Admin/QuoteController.php

namespace App\Controller\Admin;

use App\Entity\System\Quote;
use App\Form\QuoteType;
use App\Repository\QuoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/quote')]
#[IsGranted('ROLE_ADMIN')]
class QuoteController extends AbstractController
{
    #[Route('/', name: 'admin_quote_index', methods: ['GET'])]
    public function index(QuoteRepository $quoteRepository, Request $request): Response
    {
        $status = $request->query->get('status', 'all');
        
        $queryBuilder = $quoteRepository->createQueryBuilder('q')
            ->orderBy('q.createdAt', 'DESC');

        if ($status === 'active') {
            $queryBuilder->andWhere('q.isActive = :active')
                ->setParameter('active', true);
        } elseif ($status === 'inactive') {
            $queryBuilder->andWhere('q.isActive = :active')
                ->setParameter('active', false);
        }

        $quotes = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/quote/index.html.twig', [
            'quotes' => $quotes,
            'current_status' => $status,
        ]);
    }

    #[Route('/new', name: 'admin_quote_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $quote = new Quote();
        $form = $this->createForm(QuoteType::class, $quote);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($quote);
            $entityManager->flush();

            $this->addFlash('success', 'Цитату успішно додано!');
            return $this->redirectToRoute('admin_quote_index');
        }

        return $this->render('admin/quote/new.html.twig', [
            'quote' => $quote,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_quote_show', methods: ['GET'])]
    public function show(Quote $quote): Response
    {
        return $this->render('admin/quote/show.html.twig', [
            'quote' => $quote,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_quote_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Quote $quote, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(QuoteType::class, $quote);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Цитату успішно оновлено!');
            return $this->redirectToRoute('admin_quote_index');
        }

        return $this->render('admin/quote/edit.html.twig', [
            'quote' => $quote,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_quote_delete', methods: ['POST'])]
    public function delete(Request $request, Quote $quote, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$quote->getId(), $request->request->get('_token'))) {
            $entityManager->remove($quote);
            $entityManager->flush();
            
            $this->addFlash('success', 'Цитату успішно видалено!');
        }

        return $this->redirectToRoute('admin_quote_index');
    }

    #[Route('/{id}/toggle-active', name: 'admin_quote_toggle_active', methods: ['POST'])]
    public function toggleActive(Request $request, Quote $quote, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle-active'.$quote->getId(), $request->request->get('_token'))) {
            $quote->setIsActive(!$quote->isActive());
            $entityManager->flush();

            $status = $quote->isActive() ? 'активовано' : 'деактивовано';
            $this->addFlash('success', "Цитату успішно {$status}!");
        }

        return $this->redirectToRoute('admin_quote_index');
    }
}