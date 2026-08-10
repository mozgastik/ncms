<?php
// src/Service/MaintenanceService.php

namespace App\Service;

use App\Entity\System\MaintenanceSettings;
use App\Repository\MaintenanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface; // ← Додайте це
use Psr\Log\LoggerInterface;

class MaintenanceService
{
    private ?MaintenanceSettings $cachedSettings = null;

    public function __construct(
        private MaintenanceSettingsRepository $repository,
        private EntityManagerInterface $entityManager,
        private AuthorizationCheckerInterface $security,
        private TokenStorageInterface $tokenStorage, // ← Додайте це
        private LoggerInterface $logger,
    ) {}

    public function getSettings(): MaintenanceSettings
    {
        if ($this->cachedSettings !== null) {
            return $this->cachedSettings;
        }

        $settings = $this->repository->getSettings();
        
        if (!$settings->getId()) {
            $this->entityManager->persist($settings);
            $this->entityManager->flush();
        }
        
        $this->cachedSettings = $settings;
        return $settings;
    }

    public function updateSettings(MaintenanceSettings $settings): void
    {
        $settings->setUpdatedAt(new \DateTimeImmutable());
        
        $this->logSettingsChange($settings);
        
        if (!$this->entityManager->contains($settings)) {
            $this->entityManager->persist($settings);
        }
        
        $this->entityManager->flush();
        $this->cachedSettings = $settings;
    }

    public function enable(): void
    {
        $settings = $this->getSettings();
        $settings->setEnabled(true);
        $settings->setLastEnabledAt(new \DateTimeImmutable());
        $settings->incrementTotalToggles();
        $this->updateSettings($settings);
        
        $this->logger->info('Maintenance mode enabled by user: ' . $this->getCurrentUser());
    }

    public function disable(): void
    {
        $settings = $this->getSettings();
        $settings->setEnabled(false);
        $settings->setLastDisabledAt(new \DateTimeImmutable());
        $settings->incrementTotalToggles();
        $this->updateSettings($settings);
        
        $this->logger->info('Maintenance mode disabled by user: ' . $this->getCurrentUser());
    }

    public function toggle(): bool
    {
        $settings = $this->getSettings();
        $newState = !$settings->isEnabled();
        $settings->setEnabled($newState);
        
        if ($newState) {
            $settings->setLastEnabledAt(new \DateTimeImmutable());
        } else {
            $settings->setLastDisabledAt(new \DateTimeImmutable());
        }
        
        $settings->incrementTotalToggles();
        $this->updateSettings($settings);
        
        $this->logger->info('Maintenance mode toggled to: ' . ($newState ? 'ON' : 'OFF') . ' by user: ' . $this->getCurrentUser());
        
        return $newState;
    }

    public function setEnabled(bool $enabled): void
    {
        $settings = $this->getSettings();
        $settings->setEnabled($enabled);
        
        if ($enabled) {
            $settings->setLastEnabledAt(new \DateTimeImmutable());
        } else {
            $settings->setLastDisabledAt(new \DateTimeImmutable());
        }
        
        $settings->incrementTotalToggles();
        $this->updateSettings($settings);
        
        $this->logger->info('Maintenance mode set to: ' . ($enabled ? 'ON' : 'OFF') . ' by user: ' . $this->getCurrentUser());
    }

    public function shouldShowMaintenance(Request $request): bool
    {
        $settings = $this->getSettings();
        
        if (!$settings->isActive()) {
            return false;
        }

        $now = new \DateTimeImmutable();
        if ($settings->getStartAt() && $now < $settings->getStartAt()) {
            return false;
        }
        if ($settings->getEndAt() && $now > $settings->getEndAt()) {
            return false;
        }

        $clientIp = $request->getClientIp();
        $allowedIps = $settings->getAllowedIps();
        if ($allowedIps && is_array($allowedIps) && in_array($clientIp, $allowedIps)) {
            return false;
        }

        $currentRoute = $request->attributes->get('_route');
        $allowedRoutes = $settings->getAllowedRoutes();
        if ($allowedRoutes && is_array($allowedRoutes) && in_array($currentRoute, $allowedRoutes)) {
            return false;
        }

        if ($settings->isAllowAdminAccess() && $this->security->isGranted('ROLE_ADMIN')) {
            return false;
        }

        if ($request->isXmlHttpRequest()) {
            return false;
        }

        return true;
    }

    public function isEnabled(): bool
    {
        return $this->getSettings()->isEnabled();
    }

    public function isActive(): bool
    {
        return $this->getSettings()->isActive();
    }

    public function getRemainingTime(): ?string
    {
        return $this->getSettings()->getRemainingTime();
    }

    public function getStatistics(): array
    {
        $settings = $this->getSettings();
        
        $totalEnabledTime = $this->calculateTotalEnabledTime();
        
        return [
            'isEnabled' => $settings->isEnabled(),
            'isActive' => $settings->isActive(),
            'title' => $settings->getTitle(),
            'message' => $settings->getMessage(),
            'startAt' => $settings->getStartAt(),
            'endAt' => $settings->getEndAt(),
            'remainingTime' => $settings->getRemainingTime(),
            'totalEnabledTime' => $totalEnabledTime,
            'totalToggles' => $settings->getTotalToggles(),
            'lastEnabledAt' => $settings->getLastEnabledAt(),
            'lastDisabledAt' => $settings->getLastDisabledAt(),
            'allowedIpsCount' => count($settings->getAllowedIps() ?: []),
            'allowedRoutesCount' => count($settings->getAllowedRoutes() ?: []),
            'backgroundColor' => $settings->getBackgroundColor(),
            'textColor' => $settings->getTextColor(),
            'accentColor' => $settings->getAccentColor(),
        ];
    }

    public function getLogs(): array
    {
        return $this->repository->findRecentLogs(100);
    }

    public function clearLogs(): void
    {
        $this->repository->clearLogs();
        $this->logger->info('Maintenance logs cleared by user: ' . $this->getCurrentUser());
    }

    private function calculateTotalEnabledTime(): ?string
    {
        $settings = $this->getSettings();
        
        if (!$settings->getLastEnabledAt()) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $start = $settings->getLastEnabledAt();
        
        if (!$settings->isEnabled()) {
            $end = $settings->getLastDisabledAt() ?? $now;
        } else {
            $end = $now;
        }

        $diff = $start->diff($end);
        
        $hours = $diff->h + ($diff->days * 24);
        $minutes = $diff->i;
        
        return sprintf('%d год %d хв', $hours, $minutes);
    }

    private function logSettingsChange(MaintenanceSettings $settings): void
    {
        $changes = [
            'enabled' => $settings->isEnabled(),
            'allowAdminAccess' => $settings->isAllowAdminAccess(),
            'title' => $settings->getTitle(),
            'startAt' => $settings->getStartAt()?->format('Y-m-d H:i:s'),
            'endAt' => $settings->getEndAt()?->format('Y-m-d H:i:s'),
        ];
        
        $this->logger->info('Maintenance settings updated', [
            'changes' => $changes,
            'user' => $this->getCurrentUser(),
        ]);
    }

    // ============================================
    // ВИПРАВЛЕНО: метод для отримання поточного користувача
    // ============================================
    
    private function getCurrentUser(): string
    {
        // Отримуємо токен
        $token = $this->tokenStorage->getToken();
        
        if ($token && $token->getUser()) {
            $user = $token->getUser();
            // Якщо це об'єкт UserInterface
            if (method_exists($user, 'getUserIdentifier')) {
                return $user->getUserIdentifier();
            }
            if (method_exists($user, 'getUsername')) {
                return $user->getUsername();
            }
            return (string) $user;
        }
        
        return 'system';
    }

    // ============================================
    // МЕТОДИ ДЛЯ РОБОТИ З НАЛАШТУВАННЯМИ
    // ============================================

    public function setTitle(string $title): void
    {
        $settings = $this->getSettings();
        $settings->setTitle($title);
        $this->updateSettings($settings);
    }

    public function setMessage(string $message): void
    {
        $settings = $this->getSettings();
        $settings->setMessage($message);
        $this->updateSettings($settings);
    }

    public function setStartAt(?\DateTimeImmutable $startAt): void
    {
        $settings = $this->getSettings();
        $settings->setStartAt($startAt);
        $this->updateSettings($settings);
    }

    public function setEndAt(?\DateTimeImmutable $endAt): void
    {
        $settings = $this->getSettings();
        $settings->setEndAt($endAt);
        $this->updateSettings($settings);
    }

    public function setBackgroundColor(string $color): void
    {
        $settings = $this->getSettings();
        $settings->setBackgroundColor($color);
        $this->updateSettings($settings);
    }

    public function setTextColor(string $color): void
    {
        $settings = $this->getSettings();
        $settings->setTextColor($color);
        $this->updateSettings($settings);
    }

    public function setAccentColor(string $color): void
    {
        $settings = $this->getSettings();
        $settings->setAccentColor($color);
        $this->updateSettings($settings);
    }

    public function setAllowAdminAccess(bool $allow): void
    {
        $settings = $this->getSettings();
        $settings->setAllowAdminAccess($allow);
        $this->updateSettings($settings);
    }

    public function addAllowedIp(string $ip): void
    {
        $settings = $this->getSettings();
        $ips = $settings->getAllowedIps() ?: [];
        if (!in_array($ip, $ips)) {
            $ips[] = $ip;
            $settings->setAllowedIps($ips);
            $this->updateSettings($settings);
        }
    }

    public function removeAllowedIp(string $ip): void
    {
        $settings = $this->getSettings();
        $ips = $settings->getAllowedIps() ?: [];
        $ips = array_filter($ips, fn($i) => $i !== $ip);
        $settings->setAllowedIps(array_values($ips));
        $this->updateSettings($settings);
    }

    public function addAllowedRoute(string $route): void
    {
        $settings = $this->getSettings();
        $routes = $settings->getAllowedRoutes() ?: [];
        if (!in_array($route, $routes)) {
            $routes[] = $route;
            $settings->setAllowedRoutes($routes);
            $this->updateSettings($settings);
        }
    }

    public function removeAllowedRoute(string $route): void
    {
        $settings = $this->getSettings();
        $routes = $settings->getAllowedRoutes() ?: [];
        $routes = array_filter($routes, fn($r) => $r !== $route);
        $settings->setAllowedRoutes(array_values($routes));
        $this->updateSettings($settings);
    }

    public function clearCache(): void
    {
        $this->cachedSettings = null;
    }

}