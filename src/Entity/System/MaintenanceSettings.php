<?php
// src/Entity/MaintenanceSettings.php

namespace App\Entity\System;

use App\Repository\MaintenanceSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MaintenanceSettingsRepository::class)]
#[ORM\Table(name: 'maintenance_settings')]
class MaintenanceSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?bool $enabled = false;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $title = 'Сайт на технічному обслуговуванні';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = 'Ми проводимо технічні роботи. Будь ласка, завітайте пізніше.';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $allowedIps = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $allowedRoutes = ['admin_login', 'admin_dashboard', 'app_login'];

    #[ORM\Column]
    private ?bool $allowAdminAccess = true;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $backgroundColor = '#1e293b';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $textColor = '#ffffff';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $accentColor = '#3b82f6';

    // ============================================
    // НОВІ ПОЛЯ
    // ============================================

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalToggles = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastEnabledAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastDisabledAt = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $maxRetries = 3;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $retryDelay = 60;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $showCountdown = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $showSocialLinks = false;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $socialLinks = [
        'facebook' => null,
        'twitter' => null,
        'instagram' => null,
        'telegram' => null,
    ];

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $contactEmail = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ============================================
    // ГЕТТЕРИ ТА СЕТТЕРИ
    // ============================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function getEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(?\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;
        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(?\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;
        return $this;
    }

    public function getAllowedIps(): ?array
    {
        return $this->allowedIps;
    }

    public function setAllowedIps(?array $allowedIps): static
    {
        $this->allowedIps = $allowedIps;
        return $this;
    }

    public function addAllowedIp(string $ip): static
    {
        if (!is_array($this->allowedIps)) {
            $this->allowedIps = [];
        }
        if (!in_array($ip, $this->allowedIps)) {
            $this->allowedIps[] = $ip;
        }
        return $this;
    }

    public function removeAllowedIp(string $ip): static
    {
        if (is_array($this->allowedIps)) {
            $this->allowedIps = array_filter(
                $this->allowedIps, 
                fn($item) => $item !== $ip
            );
            $this->allowedIps = array_values($this->allowedIps);
        }
        return $this;
    }

    public function getAllowedRoutes(): ?array
    {
        return $this->allowedRoutes;
    }

    public function setAllowedRoutes(?array $allowedRoutes): static
    {
        $this->allowedRoutes = $allowedRoutes;
        return $this;
    }

    public function addAllowedRoute(string $route): static
    {
        if (!is_array($this->allowedRoutes)) {
            $this->allowedRoutes = [];
        }
        if (!in_array($route, $this->allowedRoutes)) {
            $this->allowedRoutes[] = $route;
        }
        return $this;
    }

    public function removeAllowedRoute(string $route): static
    {
        if (is_array($this->allowedRoutes)) {
            $this->allowedRoutes = array_filter(
                $this->allowedRoutes, 
                fn($item) => $item !== $route
            );
            $this->allowedRoutes = array_values($this->allowedRoutes);
        }
        return $this;
    }

    public function isAllowAdminAccess(): ?bool
    {
        return $this->allowAdminAccess;
    }

    public function setAllowAdminAccess(bool $allowAdminAccess): static
    {
        $this->allowAdminAccess = $allowAdminAccess;
        return $this;
    }

    public function getBackgroundColor(): ?string
    {
        return $this->backgroundColor;
    }

    public function setBackgroundColor(?string $backgroundColor): static
    {
        $this->backgroundColor = $backgroundColor;
        return $this;
    }

    public function getTextColor(): ?string
    {
        return $this->textColor;
    }

    public function setTextColor(?string $textColor): static
    {
        $this->textColor = $textColor;
        return $this;
    }

    public function getAccentColor(): ?string
    {
        return $this->accentColor;
    }

    public function setAccentColor(?string $accentColor): static
    {
        $this->accentColor = $accentColor;
        return $this;
    }

    // ============================================
    // НОВІ ГЕТТЕРИ ТА СЕТТЕРИ
    // ============================================

    public function getTotalToggles(): int
    {
        return $this->totalToggles;
    }

    public function setTotalToggles(int $totalToggles): static
    {
        $this->totalToggles = $totalToggles;
        return $this;
    }

    public function incrementTotalToggles(): static
    {
        $this->totalToggles++;
        return $this;
    }

    public function getLastEnabledAt(): ?\DateTimeImmutable
    {
        return $this->lastEnabledAt;
    }

    public function setLastEnabledAt(?\DateTimeImmutable $lastEnabledAt): static
    {
        $this->lastEnabledAt = $lastEnabledAt;
        return $this;
    }

    public function getLastDisabledAt(): ?\DateTimeImmutable
    {
        return $this->lastDisabledAt;
    }

    public function setLastDisabledAt(?\DateTimeImmutable $lastDisabledAt): static
    {
        $this->lastDisabledAt = $lastDisabledAt;
        return $this;
    }

    public function getMaxRetries(): ?int
    {
        return $this->maxRetries;
    }

    public function setMaxRetries(?int $maxRetries): static
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    public function getRetryDelay(): ?int
    {
        return $this->retryDelay;
    }

    public function setRetryDelay(?int $retryDelay): static
    {
        $this->retryDelay = $retryDelay;
        return $this;
    }

    public function isShowCountdown(): bool
    {
        return $this->showCountdown;
    }

    public function setShowCountdown(bool $showCountdown): static
    {
        $this->showCountdown = $showCountdown;
        return $this;
    }

    public function isShowSocialLinks(): bool
    {
        return $this->showSocialLinks;
    }

    public function setShowSocialLinks(bool $showSocialLinks): static
    {
        $this->showSocialLinks = $showSocialLinks;
        return $this;
    }

    public function getSocialLinks(): ?array
    {
        return $this->socialLinks;
    }

    public function setSocialLinks(?array $socialLinks): static
    {
        $this->socialLinks = $socialLinks;
        return $this;
    }

    public function getSocialLink(string $platform): ?string
    {
        return $this->socialLinks[$platform] ?? null;
    }

    public function setSocialLink(string $platform, ?string $url): static
    {
        if (!is_array($this->socialLinks)) {
            $this->socialLinks = [];
        }
        $this->socialLinks[$platform] = $url;
        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): static
    {
        $this->contactEmail = $contactEmail;
        return $this;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): static
    {
        $this->contactPhone = $contactPhone;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // ============================================
    // ДОПОМІЖНІ МЕТОДИ
    // ============================================

    /**
     * Перевіряє чи режим обслуговування активний зараз
     */
    public function isActive(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $now = new \DateTimeImmutable();

        if ($this->startAt && $now < $this->startAt) {
            return false;
        }

        if ($this->endAt && $now > $this->endAt) {
            return false;
        }

        return true;
    }

    /**
     * Отримує час, що залишився до кінця обслуговування
     */
    public function getRemainingTime(): ?string
    {
        if (!$this->endAt) {
            return null;
        }

        $now = new \DateTimeImmutable();
        if ($now > $this->endAt) {
            return null;
        }

        $diff = $now->diff($this->endAt);
        
        $parts = [];
        if ($diff->d > 0) {
            $parts[] = $diff->d . ' ' . $this->pluralize($diff->d, 'день', 'дні', 'днів');
        }
        if ($diff->h > 0) {
            $parts[] = $diff->h . ' ' . $this->pluralize($diff->h, 'година', 'години', 'годин');
        }
        if ($diff->i > 0) {
            $parts[] = $diff->i . ' ' . $this->pluralize($diff->i, 'хвилина', 'хвилини', 'хвилин');
        }
        
        return implode(' ', $parts) ?: 'менше хвилини';
    }

    /**
     * Отримує загальний час роботи в режимі обслуговування
     */
    public function getTotalEnabledTime(): ?string
    {
        if (!$this->lastEnabledAt) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $start = $this->lastEnabledAt;
        
        if (!$this->enabled) {
            $end = $this->lastDisabledAt ?? $now;
        } else {
            $end = $now;
        }

        $diff = $start->diff($end);
        
        $hours = $diff->h + ($diff->days * 24);
        $minutes = $diff->i;
        
        return sprintf('%d год %d хв', $hours, $minutes);
    }

    /**
     * Перевіряє чи є IP в списку дозволених
     */
    public function isIpAllowed(string $ip): bool
    {
        return is_array($this->allowedIps) && in_array($ip, $this->allowedIps);
    }

    /**
     * Перевіряє чи є маршрут в списку дозволених
     */
    public function isRouteAllowed(string $route): bool
    {
        return is_array($this->allowedRoutes) && in_array($route, $this->allowedRoutes);
    }

    /**
     * Отримує статистику в масиві
     */
    public function getStatistics(): array
    {
        return [
            'id' => $this->id,
            'enabled' => $this->enabled,
            'isActive' => $this->isActive(),
            'title' => $this->title,
            'message' => $this->message,
            'startAt' => $this->startAt?->format('c'),
            'endAt' => $this->endAt?->format('c'),
            'remainingTime' => $this->getRemainingTime(),
            'totalToggles' => $this->totalToggles,
            'lastEnabledAt' => $this->lastEnabledAt?->format('c'),
            'lastDisabledAt' => $this->lastDisabledAt?->format('c'),
            'totalEnabledTime' => $this->getTotalEnabledTime(),
            'allowedIpsCount' => count($this->allowedIps ?: []),
            'allowedRoutesCount' => count($this->allowedRoutes ?: []),
            'allowAdminAccess' => $this->allowAdminAccess,
            'backgroundColor' => $this->backgroundColor,
            'textColor' => $this->textColor,
            'accentColor' => $this->accentColor,
            'showCountdown' => $this->showCountdown,
            'showSocialLinks' => $this->showSocialLinks,
            'contactEmail' => $this->contactEmail,
            'contactPhone' => $this->contactPhone,
            'updatedAt' => $this->updatedAt?->format('c'),
        ];
    }

    /**
     * Допоміжний метод для відмінювання слів
     */
    private function pluralize(int $number, string $one, string $few, string $many): string
    {
        $mod10 = $number % 10;
        $mod100 = $number % 100;

        if ($mod100 >= 11 && $mod100 <= 19) {
            return $many;
        }

        if ($mod10 == 1) {
            return $one;
        }

        if ($mod10 >= 2 && $mod10 <= 4) {
            return $few;
        }

        return $many;
    }

    /**
     * Клонує об'єкт для збереження історії
     */
    public function createSnapshot(): self
    {
        $snapshot = clone $this;
        $snapshot->id = null;
        $snapshot->updatedAt = new \DateTimeImmutable();
        return $snapshot;
    }

    /**
     * Перевіряє чи є зміни в налаштуваннях
     */
    public function hasChanges(self $other): bool
    {
        return $this->enabled !== $other->enabled ||
               $this->title !== $other->title ||
               $this->message !== $other->message ||
               $this->startAt != $other->startAt ||
               $this->endAt != $other->endAt ||
               $this->allowAdminAccess !== $other->allowAdminAccess ||
               $this->backgroundColor !== $other->backgroundColor ||
               $this->textColor !== $other->textColor ||
               $this->accentColor !== $other->accentColor;
    }

    public function __toString(): string
    {
        return $this->title ?: 'Режим обслуговування';
    }
}