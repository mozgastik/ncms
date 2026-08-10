<?php
// src/Service/SettingsManager.php

namespace App\Service;

use App\Entity\Admin\Setting;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class SettingsManager
{
    private ?array $settings = null;
    private ?UserInterface $user = null;

    public function __construct(
        private readonly SettingRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache,
        private readonly Security $security
    ) {
        if ($this->security->getUser()) {
            $this->user = $this->security->getUser();
        }
    }

    /**
     * Отримати всі налаштування
     */
    public function all(bool $includeSystem = false): array
    {
        if ($this->settings === null) {
            $this->settings = $this->repository->getAllAsArray($includeSystem);
        }
        return $this->settings;
    }

    /**
     * Отримати значення налаштування
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        return $all[$key] ?? $default;
    }

    /**
     * Отримати об'єкт налаштування
     */
    public function getEntity(string $key): ?Setting
    {
        return $this->repository->findByKey($key);
    }

    /**
     * Встановити значення налаштування
     */
    public function set(string $key, mixed $value): self
    {
        $setting = $this->repository->findByKey($key);
        
        if (!$setting) {
            throw new \InvalidArgumentException(sprintf('Налаштування "%s" не існує', $key));
        }

        // Валідація значення
        if (!$setting->validateValue($value)) {
            throw new \InvalidArgumentException(sprintf('Невірний формат значення для налаштування "%s"', $key));
        }

        if (!$setting->isInRange($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Значення має бути в межах від %d до %d',
                $setting->getMinValue(),
                $setting->getMaxValue()
            ));
        }

        // Використовуємо метод setNormalizedValue (якщо він є в Entity)
        if (method_exists($setting, 'setNormalizedValue')) {
            $setting->setNormalizedValue($value);
        } else {
            // Fallback якщо методу немає
            if (is_array($value)) {
                $setting->setValue(json_encode($value));
            } elseif (is_bool($value)) {
                $setting->setValue($value ? '1' : '0');
            } elseif (is_null($value)) {
                $setting->setValue(null);
            } else {
                $setting->setValue((string) $value);
            }
        }
        
        if ($this->user) {
            $setting->setUpdatedBy($this->user->getUserIdentifier());
        }

        $this->entityManager->flush();
        $this->clearCache();

        return $this;
    }

    /**
     * Масове оновлення налаштувань
     */
    public function setMultiple(array $values): self
    {
        foreach ($values as $key => $value) {
            try {
                $this->set($key, $value);
            } catch (\InvalidArgumentException $e) {
                error_log($e->getMessage());
            }
        }
        
        return $this;
    }

    /**
     * Отримати публічні налаштування
     */
    public function getPublic(): array
    {
        return $this->repository->getPublicSettings();
    }

    /**
     * Отримати налаштування за групою
     */
    public function getGroup(string $group): array
    {
        return $this->repository->getByGroup($group);
    }

    /**
     * Створити нове налаштування
     */
    public function create(string $key, array $data): Setting
    {
        $setting = new Setting();
        $setting->setSettingKey($key);
        $setting->setLabel($data['label'] ?? $key);
        $setting->setType($data['type'] ?? 'text');
        $setting->setSettingGroup($data['group'] ?? 'general');
        $setting->setDescription($data['description'] ?? null);
        $setting->setOptions($data['options'] ?? null);
        $setting->setSortOrder($data['sortOrder'] ?? 0);
        $setting->setIsRequired($data['isRequired'] ?? false);
        $setting->setIsPublic($data['isPublic'] ?? false);
        $setting->setIsSystem($data['isSystem'] ?? false);
        $setting->setIsVisible($data['isVisible'] ?? true);
        $setting->setIcon($data['icon'] ?? null);
        $setting->setPlaceholder($data['placeholder'] ?? null);
        $setting->setMinValue($data['minValue'] ?? null);
        $setting->setMaxValue($data['maxValue'] ?? null);
        $setting->setMaxLength($data['maxLength'] ?? null);
        $setting->setValidationRule($data['validationRule'] ?? null);
        $setting->setIsEncrypted($data['isEncrypted'] ?? false);
        $setting->setIsReadonly(false);

        if (isset($data['value'])) {
            if (method_exists($setting, 'setNormalizedValue')) {
                $setting->setNormalizedValue($data['value']);
            } else {
                if (is_array($data['value'])) {
                    $setting->setValue(json_encode($data['value']));
                } else {
                    $setting->setValue((string) $data['value']);
                }
            }
        }

        $this->entityManager->persist($setting);
        $this->entityManager->flush();
        $this->clearCache();

        return $setting;
    }

    /**
     * Видалити налаштування (тільки якщо не системне)
     */
    public function delete(string $key): bool
    {
        $setting = $this->repository->findByKey($key);
        
        if (!$setting) {
            return false;
        }

        if ($setting->isSystem()) {
            throw new \RuntimeException('Системні налаштування не можна видаляти');
        }

        $this->entityManager->remove($setting);
        $this->entityManager->flush();
        $this->clearCache();

        return true;
    }

    /**
     * Очистити кеш
     */
    public function clearCache(): void
    {
        $this->settings = null;
        $this->repository->clearCache();
        $this->cache->delete('settings_all');
    }

    /**
     * Перевірити чи існує налаштування
     */
    public function has(string $key): bool
    {
        return $this->repository->findByKey($key) !== null;
    }

    /**
     * Отримати значення з приведенням до булевого
     */
    public function getBoolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Отримати значення з приведенням до цілого числа
     */
    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    /**
     * Отримати значення з приведенням до числа з плаваючою точкою
     */
    public function getFloat(string $key, float $default = 0.0): float
    {
        return (float) $this->get($key, $default);
    }

    /**
     * Отримати значення з приведенням до масиву
     */
    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);
        
        if (is_string($value)) {
            return json_decode($value, true) ?: explode(',', $value);
        }
        
        return (array) $value;
    }

    /**
     * Отримати всі групи налаштувань
     */
    public function getGroups(): array
    {
        $settings = $this->repository->findAll();
        $groups = [];
        
        foreach ($settings as $setting) {
            $group = $setting->getSettingGroup();
            if (!isset($groups[$group])) {
                $groups[$group] = [
                    'key' => $group,
                    'label' => $setting->getGroupLabel(),
                    'count' => 0
                ];
            }
            $groups[$group]['count']++;
        }

        ksort($groups);
        return $groups;
    }

    // ============================================
    // МЕТОДИ ДЛЯ ПОГОДИ
    // ============================================

    public function getWeatherApiKey(): ?string
    {
        return $this->get('weather.api_key');
    }

    public function getWeatherProvider(): string
    {
        return $this->get('weather.provider', 'openweathermap');
    }

    public function getWeatherCity(): string
    {
        return $this->get('weather.default_city', 'Kyiv');
    }

    public function getWeatherUnits(): string
    {
        return $this->get('weather.units', 'metric');
    }

    public function setWeatherApiKey(?string $value): self
    {
        if (!$this->has('weather.api_key')) {
            $this->create('weather.api_key', [
                'label' => 'API ключ погоди',
                'type' => 'text',
                'group' => 'api',
                'description' => 'API ключ для сервісу погоди (OpenWeatherMap або Google)',
                'isPublic' => false,
                'isSystem' => false,
                'isRequired' => false,
                'placeholder' => 'Введіть API ключ...',
                'icon' => 'fas fa-key',
                'isEncrypted' => true,
            ]);
        }
        
        return $this->set('weather.api_key', $value);
    }

    public function setWeatherProvider(string $value): self
    {
        if (!$this->has('weather.provider')) {
            $this->create('weather.provider', [
                'label' => 'Провайдер погоди',
                'type' => 'choice',
                'group' => 'api',
                'description' => 'Виберіть провайдера для отримання даних погоди',
                'options' => [
                    'openweathermap' => 'OpenWeatherMap',
                    'google' => 'Google Weather (Open-Meteo)',
                ],
                'isPublic' => false,
                'isSystem' => false,
                'isRequired' => true,
                'icon' => 'fas fa-cloud-sun',
            ]);
        }
        
        return $this->set('weather.provider', $value);
    }

    public function setWeatherCity(string $value): self
    {
        if (!$this->has('weather.default_city')) {
            $this->create('weather.default_city', [
                'label' => 'Місто за замовчуванням',
                'type' => 'text',
                'group' => 'api',
                'description' => 'Місто, яке буде використовуватися якщо геолокація недоступна',
                'isPublic' => false,
                'isSystem' => false,
                'isRequired' => true,
                'placeholder' => 'Наприклад: Kyiv, London, Paris',
                'icon' => 'fas fa-city',
                'validationRule' => '/^[a-zA-Zа-яА-ЯёЁЇїІіЄєҐґ\s\-\.]+$/u',
                'maxLength' => 100,
            ]);
        }
        
        return $this->set('weather.default_city', $value);
    }

    public function setWeatherUnits(string $value): self
    {
        if (!$this->has('weather.units')) {
            $this->create('weather.units', [
                'label' => 'Одиниці виміру',
                'type' => 'choice',
                'group' => 'api',
                'description' => 'Виберіть одиниці виміру для відображення погоди',
                'options' => [
                    'metric' => 'Метричні (°C, км/год)',
                    'imperial' => 'Імперські (°F, миль/год)',
                ],
                'isPublic' => false,
                'isSystem' => false,
                'isRequired' => true,
                'icon' => 'fas fa-ruler',
            ]);
        }
        
        return $this->set('weather.units', $value);
    }

    public function getWeatherConfig(): array
    {
        return [
            'api_key' => $this->getWeatherApiKey(),
            'provider' => $this->getWeatherProvider(),
            'default_city' => $this->getWeatherCity(),
            'units' => $this->getWeatherUnits(),
        ];
    }

    public function isWeatherConfigured(): bool
    {
        return !empty($this->getWeatherApiKey());
    }
}