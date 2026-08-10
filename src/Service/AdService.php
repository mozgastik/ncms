<?php
// src/Service/AdService.php

namespace App\Service;

use App\Entity\Admin\Ad;
use App\Repository\AdRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class AdService
{
    public function __construct(
        private AdRepository $adRepository,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    /**
     * Отримує список активних банерів для зони
     */
    public function getAdsForZone(string $zoneCode): array
    {
        $ads = $this->adRepository->findActiveByZone($zoneCode);
        // Якщо потрібна ротація — можна перемішати або вибрати випадковий
        return $ads;
    }

    /**
     * Рендерить HTML для заданої зони.
     * Якщо банерів кілька, можна обирати випадковий або показувати перший.
     */
    public function renderZone(string $zoneCode, array $options = []): string
    {
        $ads = $this->getAdsForZone($zoneCode);
        if (empty($ads)) return '';

        // Оберемо випадковий або за пріоритетом (перший)
        $ad = $ads[array_rand($ads)]; // випадковий
        // або $ad = $ads[0]; // найвищий пріоритет

        return $this->renderAd($ad, $options);
    }

    /**
     * Рендерить HTML для конкретного банера
     */
    public function renderAd(Ad $ad, array $options = []): string
    {
        return match ($ad->getType()) {
            Ad::TYPE_IMAGE => $this->renderImageAd($ad, $options),
            Ad::TYPE_HTML  => $ad->getCode() ?? '',
            Ad::TYPE_SCRIPT => $ad->getCode() ?? '',
            default => '',
        };
    }

    private function renderImageAd(Ad $ad, array $options): string
    {
        $width = $options['width'] ?? $ad->getZone()->getWidth();
        $height = $options['height'] ?? $ad->getZone()->getHeight();

        $html = sprintf('<img src="%s" alt="%s" style="width:%s;height:%s;max-width:100%%;" />',
            $ad->getImage(),
            htmlspecialchars($ad->getTitle()),
            $width,
            $height
        );

        if ($ad->getLink()) {
            $html = sprintf('<a href="%s" target="_blank" rel="nofollow noopener">%s</a>',
                $ad->getLink(),
                $html
            );
        }

        return $html;
    }
}