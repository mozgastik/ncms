<?php
// src/Twig/AdExtension.php

namespace App\Twig;

use App\Service\AdService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdExtension extends AbstractExtension
{
    public function __construct(private AdService $adService) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ad_zone', [$this, 'renderAdZone'], ['is_safe' => ['html']]),
            new TwigFunction('ad_banner', [$this, 'renderAdBanner'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Рендерить рекламну зону
     * {{ ad_zone('sidebar') }}
     */
    public function renderAdZone(string $zoneCode, array $options = []): string
    {
        return $this->adService->renderZone($zoneCode, $options);
    }

    /**
     * Рендерить конкретний банер за його ID (якщо потрібно)
     */
    public function renderAdBanner(int $adId): string
    {
        // Можна додати метод в AdService для отримання одного банера
        return '';
    }
}