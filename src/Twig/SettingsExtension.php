<?php

namespace App\Twig;

use App\Service\SettingsManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SettingsExtension extends AbstractExtension
{
    public function __construct(
        private readonly SettingsManager $settingsManager
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('setting', $this->getSetting(...)),
            new TwigFunction('setting_bool', $this->getSettingBool(...)),
            new TwigFunction('setting_int', $this->getSettingInt(...)),
            new TwigFunction('setting_array', $this->getSettingArray(...)),
            new TwigFunction('settings_all', $this->getAllSettings(...)),
            new TwigFunction('settings_group', $this->getSettingsGroup(...)),
            new TwigFunction('setting_has', $this->hasSetting(...)),
        ];
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settingsManager->get($key, $default);
    }

    public function getSettingBool(string $key, bool $default = false): bool
    {
        return $this->settingsManager->getBoolean($key, $default);
    }

    public function getSettingInt(string $key, int $default = 0): int
    {
        return $this->settingsManager->getInt($key, $default);
    }

    public function getSettingArray(string $key, array $default = []): array
    {
        return $this->settingsManager->getArray($key, $default);
    }

    public function getAllSettings(): array
    {
        return $this->settingsManager->all();
    }

    public function getSettingsGroup(string $group): array
    {
        return $this->settingsManager->getGroup($group);
    }

    public function hasSetting(string $key): bool
    {
        return $this->settingsManager->has($key);
    }
}