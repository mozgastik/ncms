<?php
// src/Controller/QuoteController.php

namespace App\Controller\Front;

use App\Entity\System\Quote;
use App\Repository\QuoteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class QuoteController extends AbstractController
{
    #[Route('/quotes', name: 'app_quote_index')]
    public function index(QuoteRepository $quoteRepository, Request $request): Response
    {
        $category = $request->query->get('category');
        $page = $request->query->getInt('page', 1);
        $limit = 20;

        if ($category) {
            $quotes = $quoteRepository->findByCategory($category);
        } else {
            $quotes = $quoteRepository->findActiveQuotes();
        }

        // Проста пагінація
        $total = count($quotes);
        $pages = ceil($total / $limit);
        $offset = ($page - 1) * $limit;
        $paginatedQuotes = array_slice($quotes, $offset, $limit);

        $categories = $quoteRepository->getCategories();

        return $this->render('quote/index.html.twig', [
            'quotes' => $paginatedQuotes,
            'categories' => $categories,
            'current_category' => $category,
            'current_page' => $page,
            'total_pages' => $pages,
            'total_quotes' => $total,
        ]);
    }

    #[Route('/quote/{id}', name: 'app_quote_show')]
    public function show(Quote $quote, QuoteRepository $quoteRepository): Response
    {
        // Збільшуємо лічильник переглядів
        $quote->incrementViews();
        $quoteRepository->save($quote, true);

        return $this->render('quote/show.html.twig', [
            'quote' => $quote,
        ]);
    }

    #[Route('/quote-of-the-day', name: 'app_quote_of_the_day')]
    public function quoteOfTheDay(QuoteRepository $quoteRepository): Response
    {
        $quote = $quoteRepository->findQuoteOfTheDay();

        if (!$quote) {
            throw $this->createNotFoundException('Цитату дня не знайдено');
        }

        // Збільшуємо лічильник переглядів
        $quote->incrementViews();
        $quoteRepository->save($quote, true);

        return $this->render('quote/of_the_day.html.twig', [
            'quote' => $quote,
        ]);
    }

    #[Route('/api/quote-of-the-day', name: 'api_quote_of_the_day', methods: ['GET'])]
    public function apiQuoteOfTheDay(QuoteRepository $quoteRepository): JsonResponse
    {
        $quote = $quoteRepository->findQuoteOfTheDay();

        if (!$quote) {
            return $this->json(['error' => 'Цитату дня не знайдено'], 404);
        }

        // Збільшуємо лічильник переглядів
        $quote->incrementViews();
        $quoteRepository->save($quote, true);

        return $this->json([
            'id' => $quote->getId(),
            'content' => $quote->getContent(),
            'author' => $quote->getAuthor(),
            'source' => $quote->getSource(),
            'category' => $quote->getCategory(),
            'views' => $quote->getViews(),
            'date' => $quote->getDisplayDate() ? $quote->getDisplayDate()->format('Y-m-d') : null,
        ]);
    }

    #[Route('/api/random-quote', name: 'api_random_quote', methods: ['GET'])]
    public function apiRandomQuote(QuoteRepository $quoteRepository): JsonResponse
    {
        $quote = $quoteRepository->findRandomQuote();

        if (!$quote) {
            return $this->json(['error' => 'Цитату не знайдено'], 404);
        }

        return $this->json([
            'id' => $quote->getId(),
            'content' => $quote->getContent(),
            'author' => $quote->getAuthor(),
            'source' => $quote->getSource(),
            'category' => $quote->getCategory(),
        ]);
    }

    #[Route('/popular-quotes', name: 'app_popular_quotes')]
    public function popularQuotes(QuoteRepository $quoteRepository): Response
    {
        $quotes = $quoteRepository->getPopularQuotes(20);

        return $this->render('quote/popular.html.twig', [
            'quotes' => $quotes,
        ]);
    }
}