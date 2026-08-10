<?php

namespace App\Service;

class ReadingTimeCalculator
{
    private const WORDS_PER_MINUTE = 200;
    private const IMAGE_READING_TIME = 12; // секунд на зображення
    
    /**
     * Розраховує приблизний час читання тексту
     */
    public function calculate(string $content): int
    {
        if (empty($content)) {
            return 1;
        }
        
        // Видаляємо HTML теги
        $plainText = strip_tags($content);
        
        // Рахуємо слова
        $wordCount = $this->countWords($plainText);
        
        // Рахуємо зображення (приблизно)
        $imageCount = $this->countImages($content);
        $imageTime = $imageCount * self::IMAGE_READING_TIME / 60; // переводимо в хвилини
        
        // Розраховуємо хвилини на основі слів
        $wordTime = ceil($wordCount / self::WORDS_PER_MINUTE);
        
        // Загальний час (словa + зображення)
        $totalTime = $wordTime + $imageTime;
        
        // Мінімум 1 хвилина
        return max(1, (int) $totalTime);
    }
    
    /**
     * Підрахунок слів у тексті
     */
    private function countWords(string $text): int
    {
        // Видаляємо зайві пробіли
        $text = trim($text);
        
        if (empty($text)) {
            return 0;
        }
        
        // Замінюємо всі не-словесні символи на пробіли
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        
        // Розділяємо на слова
        $words = preg_split('/\s+/', $text);
        
        // Фільтруємо порожні елементи
        $words = array_filter($words, function($word) {
            return !empty($word);
        });
        
        return count($words);
    }
    
    /**
     * Підрахунок зображень у HTML
     */
    private function countImages(string $html): int
    {
        if (empty($html)) {
            return 0;
        }
        
        // Знаходимо всі теги img
        preg_match_all('/<img\b[^>]*>/i', $html, $matches);
        
        return count($matches[0]);
    }
    
    /**
     * Форматування часу для відображення
     */
    public function format(int $minutes): string
    {
        if ($minutes < 1) {
            return 'менше хвилини';
        }
        
        if ($minutes == 1) {
            return '1 хвилина';
        }
        
        if ($minutes < 60) {
            return $minutes . ' хвилин';
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($remainingMinutes == 0) {
            return $hours . ' ' . $this->getHourWord($hours);
        }
        
        return $hours . ' ' . $this->getHourWord($hours) . ' ' . $remainingMinutes . ' хвилин';
    }
    
    /**
     * Отримання правильної форми слова "година"
     */
    private function getHourWord(int $hours): string
    {
        if ($hours % 10 == 1 && $hours % 100 != 11) {
            return 'година';
        }
        
        if (in_array($hours % 10, [2, 3, 4]) && !in_array($hours % 100, [12, 13, 14])) {
            return 'години';
        }
        
        return 'годин';
    }
    
    /**
     * Розрахунок часу читання для сутності BlogPost
     */
    public function calculateForBlogPost($blogPost): int
    {
        $content = $blogPost->getContent() ?? '';
        $excerpt = $blogPost->getExcerpt() ?? '';
        
        // Об'єднуємо контент та ексепт для більш точного розрахунку
        $fullText = $content . ' ' . $excerpt;
        
        return $this->calculate($fullText);
    }
}