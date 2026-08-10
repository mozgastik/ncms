<?php
// src/Twig/QuoteExtension.php
namespace App\Twig;

use App\Repository\QuoteRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class QuoteExtension extends AbstractExtension
{
    private $quoteRepository;
    
    public function __construct(QuoteRepository $quoteRepository)
    {
        $this->quoteRepository = $quoteRepository;
    }
    
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_random_quote', [$this, 'getRandomQuote']),
            new TwigFunction('get_quote_of_day', [$this, 'getQuoteOfDay']),
            new TwigFunction('get_quote_categories', [$this, 'getQuoteCategories']),
        ];
    }
    
    public function getRandomQuote(): ?array
    {
        try {
            $quote = $this->quoteRepository->findRandomQuote();
            
            if (!$quote) {
                return null;
            }
            
            return [
                'content' => $quote->getContent(),
                'author' => $quote->getAuthor(),
                'source' => $quote->getSource(),
                'category' => $quote->getCategory(),
            ];
            
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function getQuoteOfDay(): ?array
    {
        try {
            $quote = $this->quoteRepository->findQuoteOfTheDay();
            
            if (!$quote) {
                return $this->getRandomQuote(); // fallback
            }
            
            return [
                'content' => $quote->getContent(),
                'author' => $quote->getAuthor(),
                'source' => $quote->getSource(),
                'category' => $quote->getCategory(),
            ];
            
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function getQuoteCategories(): array
    {
        try {
            return $this->quoteRepository->getCategories();
        } catch (\Exception $e) {
            return [];
        }
    }
}