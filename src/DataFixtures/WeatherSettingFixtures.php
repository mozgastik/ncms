<?php
// src/DataFixtures/WeatherSettingFixtures.php

namespace App\DataFixtures;

use App\Entity\Setting;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class WeatherSettingFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $settings = [
            [
                'key' => 'weather.api_key',
                'label' => 'API ключ погоди',
                'type' => 'text',
                'group' => 'api',
                'description' => 'API ключ для сервісу погоди (OpenWeatherMap або Google)',
                'isPublic' => false,
                'isSystem' => false,
                'placeholder' => 'Введіть API ключ...',
                'value' => '',
            ],
            [
                'key' => 'weather.provider',
                'label' => 'Провайдер погоди',
                'type' => 'select',
                'group' => 'api',
                'description' => 'Виберіть провайдера для отримання даних погоди',
                'options' => json_encode([
                    'openweathermap' => 'OpenWeatherMap',
                    'google' => 'Google Weather (Open-Meteo)',
                ]),
                'isPublic' => false,
                'isSystem' => false,
                'value' => 'openweathermap',
            ],
            [
                'key' => 'weather.default_city',
                'label' => 'Місто за замовчуванням',
                'type' => 'text',
                'group' => 'api',
                'description' => 'Місто, яке буде використовуватися якщо геолокація недоступна',
                'isPublic' => false,
                'isSystem' => false,
                'placeholder' => 'Наприклад: Kyiv',
                'value' => 'Kyiv',
            ],
            [
                'key' => 'weather.units',
                'label' => 'Одиниці виміру',
                'type' => 'select',
                'group' => 'api',
                'description' => 'Виберіть одиниці виміру для відображення погоди',
                'options' => json_encode([
                    'metric' => 'Метричні (°C, км/год)',
                    'imperial' => 'Імперські (°F, миль/год)',
                ]),
                'isPublic' => false,
                'isSystem' => false,
                'value' => 'metric',
            ],
        ];

        foreach ($settings as $data) {
            $setting = new Setting();
            $setting->setSettingKey($data['key']);
            $setting->setLabel($data['label']);
            $setting->setType($data['type']);
            $setting->setGroup($data['group']);
            $setting->setDescription($data['description']);
            $setting->setIsPublic($data['isPublic']);
            $setting->setIsSystem($data['isSystem']);
            $setting->setPlaceholder($data['placeholder'] ?? null);
            $setting->setOptions($data['options'] ?? null);
            $setting->setNormalizedValue($data['value']);
            
            $manager->persist($setting);
        }

        $manager->flush();
    }
}