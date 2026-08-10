<?php

namespace App\Command;

use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-settings',
    description: 'Ініціалізація базових налаштувань сайту',
)]
class InitSettingsCommand extends Command
{
    private const DEFAULT_SETTINGS = [
        // Загальні налаштування
        'site_name' => [
            'label' => 'Назва сайту',
            'value' => 'Новинний портал',
            'group' => 'general',
            'type' => 'text',
            'sortOrder' => 10,
            'icon' => 'fas fa-globe',
            'placeholder' => 'Введіть назву сайту',
            'isRequired' => true,
            'isSystem' => true,
        ],
        'site_description' => [
            'label' => 'Опис сайту',
            'value' => 'Актуальні новини та статті',
            'group' => 'general',
            'type' => 'textarea',
            'sortOrder' => 20,
            'icon' => 'fas fa-align-left',
            'placeholder' => 'Короткий опис сайту',
            'maxLength' => 500,
        ],
        'site_keywords' => [
            'label' => 'Ключові слова',
            'value' => 'новини, статті, блог',
            'group' => 'general',
            'type' => 'text',
            'sortOrder' => 30,
            'icon' => 'fas fa-tags',
            'placeholder' => 'Ключові слова через кому',
            'description' => 'Для SEO оптимізації',
        ],
        'site_email' => [
            'label' => 'Email адміністратора',
            'value' => 'admin@example.com',
            'group' => 'general',
            'type' => 'email',
            'sortOrder' => 40,
            'icon' => 'fas fa-envelope',
            'isRequired' => true,
        ],
        
        // Зовнішній вигляд
        'theme_color' => [
            'label' => 'Колір теми',
            'value' => '#3b82f6',
            'group' => 'appearance',
            'type' => 'color',
            'sortOrder' => 10,
            'icon' => 'fas fa-palette',
        ],
        'logo_text' => [
            'label' => 'Текст логотипу',
            'value' => 'AdNews.FUN',
            'group' => 'appearance',
            'type' => 'text',
            'sortOrder' => 20,
            'icon' => 'fas fa-text-height',
        ],
        
        // Соціальні мережі
        'facebook_url' => [
            'label' => 'Facebook',
            'value' => '',
            'group' => 'social',
            'type' => 'url',
            'sortOrder' => 10,
            'icon' => 'fab fa-facebook',
            'placeholder' => 'https://facebook.com/...',
            'isPublic' => true,
        ],
        'twitter_url' => [
            'label' => 'Twitter',
            'value' => '',
            'group' => 'social',
            'type' => 'url',
            'sortOrder' => 20,
            'icon' => 'fab fa-twitter',
            'placeholder' => 'https://twitter.com/...',
            'isPublic' => true,
        ],
        'telegram_url' => [
            'label' => 'Telegram',
            'value' => '',
            'group' => 'social',
            'type' => 'url',
            'sortOrder' => 30,
            'icon' => 'fab fa-telegram',
            'placeholder' => 'https://t.me/...',
            'isPublic' => true,
        ],
        'instagram_url' => [
            'label' => 'Instagram',
            'value' => '',
            'group' => 'social',
            'type' => 'url',
            'sortOrder' => 40,
            'icon' => 'fab fa-instagram',
            'placeholder' => 'https://instagram.com/...',
            'isPublic' => true,
        ],
        
        // SEO
        'meta_robots' => [
            'label' => 'Robots meta',
            'value' => 'index, follow',
            'group' => 'seo',
            'type' => 'choice',
            'options' => ['index, follow', 'noindex, follow', 'index, nofollow', 'noindex, nofollow'],
            'sortOrder' => 10,
            'icon' => 'fas fa-robot',
            'description' => 'Вказівки для пошукових роботів',
        ],
        'google_analytics' => [
            'label' => 'Google Analytics ID',
            'value' => '',
            'group' => 'seo',
            'type' => 'text',
            'sortOrder' => 20,
            'icon' => 'fab fa-google',
            'placeholder' => 'UA-XXXXXXXXX-X',
        ],
        
        // Системні
        'items_per_page' => [
            'label' => 'Елементів на сторінці',
            'value' => '15',
            'group' => 'system',
            'type' => 'integer',
            'sortOrder' => 10,
            'icon' => 'fas fa-list',
            'minValue' => 5,
            'maxValue' => 100,
            'isSystem' => true,
        ],
        'cache_ttl' => [
            'label' => 'Час життя кешу (хв)',
            'value' => '60',
            'group' => 'system',
            'type' => 'integer',
            'sortOrder' => 20,
            'icon' => 'fas fa-clock',
            'minValue' => 0,
            'maxValue' => 1440,
            'isSystem' => true,
        ],
        'maintenance_mode' => [
            'label' => 'Режим технічного обслуговування',
            'value' => '0',
            'group' => 'system',
            'type' => 'boolean',
            'sortOrder' => 30,
            'icon' => 'fas fa-tools',
            'description' => 'Увімкнути режим обслуговування сайту',
            'isSystem' => true,
        ],
    ];

     public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🚀 Ініціалізація налаштувань сайту');

        $repository = $this->entityManager->getRepository(Setting::class);
        $existing = $repository->findAll();

        if (!empty($existing)) {
            $io->warning('Налаштування вже існують!');
            return Command::SUCCESS;
        }

        $io->section('Створення базових налаштувань');

        foreach (self::DEFAULT_SETTINGS as $key => $config) {
            $setting = new Setting();
            $setting->setSettingKey($key);
            $setting->setLabel($config['label']);
            $setting->setValue($config['value'] ?? '');
            $setting->setType($config['type'] ?? 'text');
            $setting->setSettingGroup($config['group'] ?? 'general'); // ← перейменовано
            $setting->setDescription($config['description'] ?? null);
            $setting->setOptions($config['options'] ?? null);
            $setting->setSortOrder($config['sortOrder'] ?? 0);
            $setting->setIsRequired($config['isRequired'] ?? false);
            $setting->setIsPublic($config['isPublic'] ?? false);
            $setting->setIsSystem($config['isSystem'] ?? false);
            $setting->setIcon($config['icon'] ?? null);
            $setting->setPlaceholder($config['placeholder'] ?? null);
            $setting->setMinValue($config['minValue'] ?? null);
            $setting->setMaxValue($config['maxValue'] ?? null);
            $setting->setMaxLength($config['maxLength'] ?? null);
            $setting->setIsVisible(true);
            
            $this->entityManager->persist($setting);
            $io->writeln(sprintf('  <fg=green>✓</> Створено: <options=bold>%s</>', $config['label']));
        }

        $this->entityManager->flush();
        
        $io->success([
            '✅ Налаштування успішно створені!',
            sprintf('📊 Всього створено: %d налаштувань', count(self::DEFAULT_SETTINGS)),
        ]);

        return Command::SUCCESS;
    }
}