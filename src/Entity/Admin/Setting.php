<?php

namespace App\Entity\Admin;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'settings')]
#[ORM\UniqueConstraint(name: 'unique_setting_key', columns: ['setting_key'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['settingKey'], message: 'Налаштування з таким ключем вже існує')]
class Setting
{
    // Константи для типів
    public const TYPES = [
        'text' => 'Текстове поле',
        'textarea' => 'Текстова область',
        'boolean' => 'Так/Ні',
        'integer' => 'Ціле число',
        'float' => 'Десяткове число',
        'email' => 'Email',
        'url' => 'URL',
        'choice' => 'Вибір зі списку',
        'color' => 'Колір',
        'date' => 'Дата',
        'datetime' => 'Дата та час',
        'json' => 'JSON',
        'array' => 'Масив',
        'file' => 'Файл',
        'image' => 'Зображення',
        'editor' => 'Текстовий редактор',
        'code' => 'Код',
    ];

    // Константи для груп
    public const GROUPS = [
        'general' => 'Загальні',
        'appearance' => 'Зовнішній вигляд',
        'social' => 'Соціальні мережі',
        'seo' => 'SEO',
        'system' => 'Системні',
        'mail' => 'Пошта',
        'security' => 'Безпека',
        'api' => 'API',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Ключ налаштування не може бути порожнім')]
    #[Assert\Length(min: 1, max: 100)]
    #[Assert\Regex(pattern: '/^[a-z0-9_]+$/')]
    private string $settingKey = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $value = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(callback: 'getAllowedTypes')]
    private string $type = 'text';

    #[ORM\Column(name: 'setting_group', length: 100)]
    #[Assert\NotBlank(message: 'Група налаштування обов\'язкова')]
    #[Assert\Length(min: 1, max: 100)]
    #[Assert\Choice(callback: 'getAllowedGroups')]
    private string $settingGroup = 'general';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    private string $label = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $description = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $options = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $sortOrder = 0;

    #[ORM\Column]
    private bool $isRequired = false;

    #[ORM\Column]
    private bool $isPublic = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $validationRule = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $placeholder = null;

    #[ORM\Column]
    private bool $isEncrypted = false;

    #[ORM\Column(nullable: true)]
    private ?int $minValue = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxValue = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $maxLength = null;

    #[ORM\Column]
    private bool $isVisible = true;

    #[ORM\Column]
    private bool $isReadonly = false;

    #[ORM\Column]
    private bool $isSystem = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $updatedBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ============================================
    // ГЕТТЕРИ ТА СЕТТЕРИ
    // ============================================

    public function getId(): ?int { return $this->id; }

    public function getSettingKey(): string { return $this->settingKey; }
    public function setSettingKey(string $settingKey): static { $this->settingKey = $settingKey; return $this; }

    public function getValue(): ?string { return $this->value; }
    public function setValue(?string $value): static { $this->value = $value; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static {
        if (!in_array($type, array_keys(self::TYPES))) {
            throw new \InvalidArgumentException('Невірний тип налаштування');
        }
        $this->type = $type; 
        return $this;
    }

    public function getSettingGroup(): string { return $this->settingGroup; }
    public function setSettingGroup(string $settingGroup): static {
        if (!in_array($settingGroup, array_keys(self::GROUPS))) {
            throw new \InvalidArgumentException('Невірна група');
        }
        $this->settingGroup = $settingGroup; 
        return $this;
    }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): static { $this->label = $label; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getOptions(): ?array { return $this->options; }
    public function setOptions(?array $options): static { $this->options = $options; return $this; }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): static { $this->sortOrder = $sortOrder; return $this; }

    public function isRequired(): bool { return $this->isRequired; }
    public function setIsRequired(bool $isRequired): static { $this->isRequired = $isRequired; return $this; }

    public function isPublic(): bool { return $this->isPublic; }
    public function setIsPublic(bool $isPublic): static { $this->isPublic = $isPublic; return $this; }

    public function getValidationRule(): ?string { return $this->validationRule; }
    public function setValidationRule(?string $validationRule): static { $this->validationRule = $validationRule; return $this; }

    public function getIcon(): ?string { return $this->icon; }
    public function setIcon(?string $icon): static { $this->icon = $icon; return $this; }

    public function getPlaceholder(): ?string { return $this->placeholder; }
    public function setPlaceholder(?string $placeholder): static { $this->placeholder = $placeholder; return $this; }

    public function isEncrypted(): bool { return $this->isEncrypted; }
    public function setIsEncrypted(bool $isEncrypted): static { $this->isEncrypted = $isEncrypted; return $this; }

    public function getMinValue(): ?int { return $this->minValue; }
    public function setMinValue(?int $minValue): static { $this->minValue = $minValue; return $this; }

    public function getMaxValue(): ?int { return $this->maxValue; }
    public function setMaxValue(?int $maxValue): static { $this->maxValue = $maxValue; return $this; }

    public function getMaxLength(): ?int { return $this->maxLength; }
    public function setMaxLength(?int $maxLength): static { $this->maxLength = $maxLength; return $this; }

    public function isVisible(): bool { return $this->isVisible; }
    public function setIsVisible(bool $isVisible): static { $this->isVisible = $isVisible; return $this; }

    public function isReadonly(): bool { return $this->isReadonly; }
    public function setIsReadonly(bool $isReadonly): static { $this->isReadonly = $isReadonly; return $this; }

    public function isSystem(): bool { return $this->isSystem; }
    public function setIsSystem(bool $isSystem): static { $this->isSystem = $isSystem; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function getUpdatedBy(): ?string { return $this->updatedBy; }
    public function setUpdatedBy(?string $updatedBy): static { $this->updatedBy = $updatedBy; return $this; }

    // ============================================
    // ДОПОМІЖНІ МЕТОДИ
    // ============================================

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Невизначений тип';
    }

    public function getGroupLabel(): string
    {
        return self::GROUPS[$this->settingGroup] ?? $this->settingGroup;
    }

    public function __toString(): string
    {
        return $this->label ?: $this->settingKey;
    }

    // ============================================
    // МЕТОДИ ДЛЯ ТИПІЗОВАНОГО ОТРИМАННЯ ЗНАЧЕНЬ
    // ============================================

    /**
     * Нормалізує булеве значення із рядка
     */
    private function normalizeBoolean(?string $value): bool
    {
        return in_array($value, ['1', 'true', 'on', 'yes', 1, true], true);
    }

    /**
     * Повертає нормалізоване булеве значення поточного налаштування
     */
    public function getBooleanValue(): bool
    {
        return $this->normalizeBoolean($this->value);
    }

    public function getIntegerValue(): ?int
    {
        return $this->value !== null ? (int) $this->value : null;
    }

    public function getFloatValue(): ?float
    {
        return $this->value !== null ? (float) $this->value : null;
    }

    public function getArrayValue(): array
    {
        if ($this->type === 'json' && $this->value) {
            return json_decode($this->value, true, 512, JSON_THROW_ON_ERROR) ?: [];
        }
        
        if ($this->type === 'array' && $this->value) {
            return array_map('trim', explode(',', $this->value));
        }
        
        return [];
    }

    // ============================================
    // ВАЛІДАЦІЯ
    // ============================================

    public function validateValue(mixed $value): bool
    {
        return match ($this->type) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'integer' => is_numeric($value) && (int) $value == $value,
            'float' => is_numeric($value),
            'boolean' => is_bool($value) || in_array($value, ['0', '1', 0, 1, 'true', 'false', 'on', 'off'], true),
            'choice' => in_array($value, $this->options ?? [], true),
            default => true,
        };
    }

    public function isInRange(mixed $value): bool
    {
        if (!is_numeric($value) || $this->type === 'boolean') {
            return true;
        }

        $numValue = (float) $value;

        if ($this->minValue !== null && $numValue < $this->minValue) {
            return false;
        }

        if ($this->maxValue !== null && $numValue > $this->maxValue) {
            return false;
        }

        return true;
    }

    public function isLengthValid(?string $value): bool
    {
        if ($this->maxLength === null || $value === null) {
            return true;
        }

        return mb_strlen($value) <= $this->maxLength;
    }

    // ============================================
    // ДОДАТКОВІ МЕТОДИ
    // ============================================

    public function hasOptions(): bool
    {
        return !empty($this->options);
    }

    public function isConfigurable(): bool
    {
        return !$this->isSystem && !$this->isReadonly;
    }

    public function canBeDeleted(): bool
    {
        return !$this->isSystem;
    }

    public static function getAllowedTypes(): array 
    { 
        return array_keys(self::TYPES); 
    }

    public static function getAllowedGroups(): array 
    { 
        return array_keys(self::GROUPS); 
    }

    // ============================================
    // НОРМАЛІЗАЦІЯ ЗНАЧЕНЬ (ТІЛЬКИ ОДИН РАЗ)
    // ============================================

    /**
     * Встановлює нормалізоване значення залежно від типу
     */
    public function setNormalizedValue(mixed $value): self
    {
        if (is_array($value)) {
            $this->value = json_encode($value, JSON_UNESCAPED_UNICODE);
        } elseif (is_bool($value)) {
            $this->value = $value ? '1' : '0';
        } elseif (is_int($value) || is_float($value)) {
            $this->value = (string) $value;
        } elseif (is_null($value)) {
            $this->value = null;
        } else {
            $this->value = (string) $value;
        }
        
        return $this;
    }

    /**
     * Отримує нормалізоване значення залежно від типу
     */
    public function getNormalizedValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            'boolean' => $this->getBooleanValue(),
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'json', 'array' => json_decode($this->value, true, 512, JSON_THROW_ON_ERROR) ?: [],
            'choice' => $this->value,
            default => $this->value,
        };
    }
}