<?php
// src/Twig/MaintenanceExtension.php

namespace App\Twig;

use App\Service\MaintenanceService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

class MaintenanceExtension extends AbstractExtension
{
    public function __construct(
        private MaintenanceService $maintenanceService
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_maintenance', [$this, 'isMaintenance']),
            new TwigFunction('maintenance_settings', [$this, 'getSettings']),
        ];
    }

    public function isMaintenance(): bool
    {
        return $this->maintenanceService->getSettings()->isActive();
    }

    public function getSettings(): array
    {
        return $this->maintenanceService->getStatistics();
    }
}