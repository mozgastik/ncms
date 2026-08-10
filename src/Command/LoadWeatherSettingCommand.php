<?php

namespace App\Command;

use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:load-weather-settings',
    description: 'Завантажує налаштування погоди в базу даних',
)]
class LoadWeatherSettingCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Примусово оновити налаштування, навіть якщо вони існують')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Показати що буде зроблено без фактичних змін')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'Встановити API ключ (наприклад: --api-key=your_key)')
            ->addOption('provider', null, InputOption::VALUE_REQUIRED, 'Встановити провайдера (openweathermap або google)')
            ->addOption('city', null, InputOption::VALUE_REQUIRED, 'Встановити місто за замовчуванням')
            ->addOption('units', null, InputOption::VALUE_REQUIRED, 'Встановити одиниці виміру (metric або imperial)')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Скинути всі налаштування погоди до значень за замовчуванням')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('🌤️ Завантаження налаштувань погоди');

        // Перевіряємо чи є налаштування
        $existingSettings = $this->entityManager->getRepository(Setting::class)
            ->findBy(['settingKey' => [
                'weather.api_key',
                'weather.provider',
                'weather.default_city',
                'weather.units'
            ]]);

        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');
        $reset = $input->getOption('reset');

        // Якщо налаштування існують і не force - питаємо
        if (!empty($existingSettings) && !$force && !$reset) {
            if (!$io->confirm('Налаштування погоди вже існують. Оновити їх?', false)) {
                $io->warning('Операція скасована');
                return Command::SUCCESS;
            }
        }

        // Якщо reset - видаляємо всі налаштування погоди
        if ($reset) {
            if (!$dryRun) {
                foreach ($existingSettings as $setting) {
                    if ($setting->canBeDeleted()) {
                        $this->entityManager->remove($setting);
                    } else {
                        $io->warning('Системне налаштування ' . $setting->getSettingKey() . ' не може бути видалено');
                    }
                }
                $this->entityManager->flush();
                $io->success('✅ Всі налаштування погоди видалено');
            } else {
                $io->note('🧪 DRY-RUN: Буде видалено ' . count($existingSettings) . ' налаштувань');
            }
        }

        // Визначаємо значення для налаштувань
        $apiKey = $input->getOption('api-key') ?? '';
        $provider = $input->getOption('provider') ?? 'openweathermap';
        $city = $input->getOption('city') ?? 'Kyiv';
        $units = $input->getOption('units') ?? 'metric';

        // Створюємо масив налаштувань
        $settings = [
            [
                'key' => 'weather.api_key',
                'label' => 'API ключ погоди',
                'type' => 'text',
                'group' => 'api',
                'description' => 'API ключ для сервісу погоди (OpenWeatherMap або Google)',
                'value' => $apiKey,
                'placeholder' => 'Введіть ваш API ключ...',
                'icon' => 'fas fa-key',
                'isPublic' => false,
                'isSystem' => false,
                'isRequired' => false,
                'isVisible' => true,
                'sortOrder' => 10,
                'validationRule' => null,
                'minValue' => null,
                'maxValue' => null,
                'maxLength' => 255,
                'isEncrypted' => true,
            ],
            [
                'key' => 'weather.provider',
                'label' => 'Провайдер погоди',
                'type' => 'choice',
                'group' => 'api',
                'description' => 'Виберіть провайдера для отримання даних погоди',
                'value' => $provider,
                'options' => [
                    'openweathermap' => 'OpenWeatherMap',
                    'google' => 'Google Weather (Open-Meteo)',
                ],
                'icon' => 'fas fa-cloud-sun',
                'isPublic' => false,
                'isSystem' => false,
                'isRequired' => true,
                'isVisible' => true,
                'sortOrder' => 20,
                'validationRule' => null,
                'minValue' => null,
                'maxValue' => null,
                'maxLength' => null,
                'isEncrypted' => false,
            ],
            [
                'key' => 'weather.default_city',
                'label' => 'Місто за замовчуванням',
                'type' => 'text',
                'group' => 'api',
                'description' => 'Місто, яке буде використовуватися якщо геолокація недоступна',
                'value' => $city,
                'placeholder' => 'Наприклад: Kyiv, London, Paris',
                'icon' => 'fas fa-city',
                'isPublic' => false,
                'isSystem' => false,
                'isRequired' => true,
                'isVisible' => true,
                'sortOrder' => 30,
                'validationRule' => '/^[a-zA-Zа-яА-ЯёЁЇїІіЄєҐґ\s\-\.]+$/u',
                'minValue' => null,
                'maxValue' => null,
                'maxLength' => 100,
                'isEncrypted' => false,
            ],
            [
                'key' => 'weather.units',
                'label' => 'Одиниці виміру',
                'type' => 'choice',
                'group' => 'api',
                'description' => 'Виберіть одиниці виміру для відображення погоди',
                'value' => $units,
                'options' => [
                    'metric' => 'Метричні (°C, км/год)',
                    'imperial' => 'Імперські (°F, миль/год)',
                ],
                'icon' => 'fas fa-ruler',
                'isPublic' => false,
                'isSystem' => false,
                'isRequired' => true,
                'isVisible' => true,
                'sortOrder' => 40,
                'validationRule' => null,
                'minValue' => null,
                'maxValue' => null,
                'maxLength' => null,
                'isEncrypted' => false,
            ],
        ];

        // Показуємо що буде зроблено
        $io->section('📋 План дій');
        $rows = [];
        foreach ($settings as $setting) {
            $exists = array_filter($existingSettings, function($s) use ($setting) {
                return $s->getSettingKey() === $setting['key'];
            });
            $action = $reset ? '🔄 Оновлення' : (empty($exists) ? '➕ Створення' : '🔄 Оновлення');
            $rows[] = [
                $setting['key'],
                $setting['value'] ?: '(порожньо)',
                $action,
                $setting['type'],
                $setting['group'],
            ];
        }

        $io->table(['Ключ', 'Значення', 'Дія', 'Тип', 'Група'], $rows);

        if ($dryRun) {
            $io->warning('🧪 DRY-RUN режим: Зміни не застосовано');
            return Command::SUCCESS;
        }

        // Зберігаємо налаштування
        $io->section('💾 Збереження налаштувань');

        $progressBar = $io->createProgressBar(count($settings));
        $progressBar->start();

        foreach ($settings as $data) {
            $setting = $this->entityManager->getRepository(Setting::class)
                ->findOneBy(['settingKey' => $data['key']]);

            if (!$setting) {
                $setting = new Setting();
                $setting->setSettingKey($data['key']);
            }

            // Встановлюємо всі поля
            $setting->setLabel($data['label']);
            $setting->setType($data['type']);
            $setting->setSettingGroup($data['group']);
            $setting->setDescription($data['description']);
            $setting->setValue($data['value']);
            $setting->setPlaceholder($data['placeholder'] ?? null);
            $setting->setIcon($data['icon'] ?? null);
            $setting->setIsPublic($data['isPublic'] ?? false);
            $setting->setIsSystem($data['isSystem'] ?? false);
            $setting->setIsRequired($data['isRequired'] ?? false);
            $setting->setIsVisible($data['isVisible'] ?? true);
            $setting->setSortOrder($data['sortOrder'] ?? 0);
            $setting->setValidationRule($data['validationRule'] ?? null);
            $setting->setMinValue($data['minValue'] ?? null);
            $setting->setMaxValue($data['maxValue'] ?? null);
            $setting->setMaxLength($data['maxLength'] ?? null);
            $setting->setIsEncrypted($data['isEncrypted'] ?? false);
            $setting->setOptions($data['options'] ?? null);
            $setting->setIsReadonly(false);

            $this->entityManager->persist($setting);
            $progressBar->advance();
        }

        $this->entityManager->flush();
        $progressBar->finish();

        $io->newLine(2);
        $io->success('✅ Налаштування погоди успішно завантажено!');

        // Показуємо поточні налаштування
        $io->section('📊 Поточні налаштування');
        $savedSettings = $this->entityManager->getRepository(Setting::class)
            ->findBy(['settingKey' => [
                'weather.api_key',
                'weather.provider',
                'weather.default_city',
                'weather.units'
            ]]);

        $rows = [];
        foreach ($savedSettings as $setting) {
            $value = $setting->getValue();
            if ($setting->getSettingKey() === 'weather.api_key' && !empty($value)) {
                $value = substr($value, 0, 10) . '...' . substr($value, -5);
            }
            $rows[] = [
                $setting->getSettingKey(),
                $value ?: '(порожньо)',
                $setting->getTypeLabel(),
                $setting->getGroupLabel(),
                $setting->getUpdatedAt() ? $setting->getUpdatedAt()->format('d.m.Y H:i') : '-',
            ];
        }

        if (!empty($rows)) {
            $io->table(['Ключ', 'Значення', 'Тип', 'Група', 'Оновлено'], $rows);
        } else {
            $io->warning('Налаштування не знайдено');
        }

        // Додаткова інформація
        $io->section('ℹ️ Додаткова інформація');
        $io->note([
            '📌 Для зміни API ключа: php bin/console app:load-weather-settings --api-key=your_key',
            '📌 Для зміни провайдера: php bin/console app:load-weather-settings --provider=google',
            '📌 Для зміни міста: php bin/console app:load-weather-settings --city=London',
            '📌 Для зміни одиниць: php bin/console app:load-weather-settings --units=imperial',
            '📌 Для скидання всіх налаштувань: php bin/console app:load-weather-settings --reset',
            '📌 Для перевірки без змін: php bin/console app:load-weather-settings --dry-run',
        ]);

        return Command::SUCCESS;
    }
}