<?php
// src/Twig/DateExtension.php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DateExtension extends AbstractExtension
{
    private string $locale;

    public function __construct(string $locale = 'uk')
    {
        $this->locale = $locale;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('month_uk', [$this, 'getMonthName'], ['is_safe' => ['html']]),
            new TwigFilter('month_uk_short', [$this, 'getMonthNameShort'], ['is_safe' => ['html']]),
            new TwigFilter('date_uk', [$this, 'formatDate'], ['is_safe' => ['html']]),
        ];
    }

    // Приймаємо DateTime або int
    public function getMonthName(\DateTimeInterface|int $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            $month = (int) $date->format('n');
        } else {
            $month = $date;
        }

        $months = [
            1 => 'Січня', 2 => 'Лютого', 3 => 'Березня',
            4 => 'Квітня', 5 => 'Травня', 6 => 'Червня',
            7 => 'Липня', 8 => 'Серпня', 9 => 'Вересня',
            10 => 'Жовтня', 11 => 'Листопада', 12 => 'Грудня'
        ];

        return $months[$month] ?? '';
    }

    // Скорочена назва місяця
    public function getMonthNameShort(\DateTimeInterface|int $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            $month = (int) $date->format('n');
        } else {
            $month = $date;
        }

        $months = [
            1 => 'Січ', 2 => 'Лют', 3 => 'Бер',
            4 => 'Кві', 5 => 'Тра', 6 => 'Чер',
            7 => 'Лип', 8 => 'Сер', 9 => 'Вер',
            10 => 'Жов', 11 => 'Лис', 12 => 'Гру'
        ];

        return $months[$month] ?? '';
    }

    // Форматування дати українською
    public function formatDate(\DateTimeInterface $date, string $format = 'd M Y'): string
    {
        $formatter = new \IntlDateFormatter(
            $this->locale,
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            null,
            null,
            $format
        );
        return $formatter->format($date);
    }
}