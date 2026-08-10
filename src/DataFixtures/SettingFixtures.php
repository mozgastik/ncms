<?php
// src/DataFixtures/SettingFixtures.php
namespace App\DataFixtures;

use App\Entity\Setting;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SettingFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $settings = [
            // General Settings
            [
                'key' => 'site_title',
                'value' => 'Мій блог',
                'type' => 'text',
                'group' => 'general',
                'label' => 'Назва сайту',
                'description' => 'Основна назва вашого сайту',
                'required' => true,
                'sortOrder' => 10,
            ],
            [
                'key' => 'site_description',
                'value' => 'Персональний блог та новини',
                'type' => 'textarea',
                'group' => 'general',
                'label' => 'Опис сайту',
                'description' => 'Короткий опис мети сайту',
                'required' => false,
                'sortOrder' => 20,
            ],
            [
                'key' => 'site_language',
                'value' => 'uk',
                'type' => 'choice',
                'group' => 'general',
                'label' => 'Мова сайту',
                'description' => 'Основна мова контенту',
                'options' => ['uk' => 'Українська', 'ru' => 'Російська', 'en' => 'English'],
                'required' => true,
                'sortOrder' => 30,
            ],

            // SEO Settings
            [
                'key' => 'meta_title',
                'value' => 'Мій блог | Новини та статті',
                'type' => 'text',
                'group' => 'seo',
                'label' => 'Meta заголовок',
                'description' => 'Заголовок для пошукових систем',
                'required' => false,
                'sortOrder' => 10,
            ],
            [
                'key' => 'meta_description',
                'value' => 'Особистий блог з цікавими статтями та новинами',
                'type' => 'textarea',
                'group' => 'seo',
                'label' => 'Meta опис',
                'description' => 'Опис сайту для пошукових систем',
                'required' => false,
                'sortOrder' => 20,
            ],
            [
                'key' => 'meta_keywords',
                'value' => 'блог, новини, статті',
                'type' => 'text',
                'group' => 'seo',
                'label' => 'Ключові слова',
                'description' => 'Ключові слова для SEO',
                'required' => false,
                'sortOrder' => 30,
            ],

            // Email Settings
            [
                'key' => 'email_from',
                'value' => 'noreply@example.com',
                'type' => 'email',
                'group' => 'email',
                'label' => 'Email відправника',
                'description' => 'Email адреса для відправки листів',
                'required' => true,
                'sortOrder' => 10,
            ],
            [
                'key' => 'email_admin',
                'value' => 'admin@example.com',
                'type' => 'email',
                'group' => 'email',
                'label' => 'Email адміністратора',
                'description' => 'Email для сповіщень адміністратора',
                'required' => true,
                'sortOrder' => 20,
            ],

            // Social Media
            [
                'key' => 'facebook_url',
                'value' => '',
                'type' => 'url',
                'group' => 'social',
                'label' => 'Facebook',
                'description' => 'Посилання на сторінку Facebook',
                'required' => false,
                'sortOrder' => 10,
            ],
            [
                'key' => 'twitter_url',
                'value' => '',
                'type' => 'url',
                'group' => 'social',
                'label' => 'Twitter/X',
                'description' => 'Посилання на профіль Twitter/X',
                'required' => false,
                'sortOrder' => 20,
            ],
            [
                'key' => 'instagram_url',
                'value' => '',
                'type' => 'url',
                'group' => 'social',
                'label' => 'Instagram',
                'description' => 'Посилання на профіль Instagram',
                'required' => false,
                'sortOrder' => 30,
            ],

            // Appearance
            [
                'key' => 'theme_mode',
                'value' => 'auto',
                'type' => 'choice',
                'group' => 'appearance',
                'label' => 'Тема',
                'description' => 'Колірна тема сайту',
                'options' => ['light' => 'Світла', 'dark' => 'Темна', 'auto' => 'Авто'],
                'required' => true,
                'sortOrder' => 10,
            ],
            [
                'key' => 'primary_color',
                'value' => '#3b82f6',
                'type' => 'text',
                'group' => 'appearance',
                'label' => 'Основний колір',
                'description' => 'Основний брендовий колір',
                'required' => true,
                'sortOrder' => 20,
            ],
        ];

        foreach ($settings as $settingData) {
            $setting = new Setting();
            $setting->setSettingKey($settingData['key']);
            $setting->setValue($settingData['value']);
            $setting->setType($settingData['type']);
            $setting->setGroup($settingData['group']);
            $setting->setLabel($settingData['label']);
            $setting->setDescription($settingData['description']);
            $setting->setOptions($settingData['options'] ?? null);
            $setting->setRequired($settingData['required']);
            $setting->setSortOrder($settingData['sortOrder']);

            $manager->persist($setting);
        }

        $manager->flush();
    }
}