<?php
// src/Form/CategoryType.php

namespace App\Form;

use App\Entity\Article\Category;
use App\Repository\CategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Назва категорії',
                'attr' => [
                    'placeholder' => 'Введіть назву категорії',
                    'class' => 'form-input',
                ],
            ])
            ->add('slug', TextType::class, [
                'label' => 'URL слаг',
                'required' => false,
                'attr' => [
                    'placeholder' => 'залиште пустим для автоматичної генерації',
                    'class' => 'form-input',
                ],
                'help' => 'Використовується в URL. Тільки маленькі літери, цифри та дефіси',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Опис',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Опис категорії...',
                    'class' => 'form-textarea',
                ],
            ])  // ← Тут була помилка - передчасне закриття дужки
            ->add('image', ChoiceType::class, [  // ← Змінено 'image' на 'icon'
                'label' => 'Іконка',
                'required' => false,
                'placeholder' => 'Виберіть іконку',
                'choices' => [
                    '📰 Новини та медіа' => [
            'Новини' => 'newspaper',
            'Статті' => 'article',
            'Блоги' => 'rss_feed',
            'Відео' => 'videocam',
            'Подкасти' => 'podcasts',
            'Галерея' => 'collections',
            'Фото' => 'photo_camera',
        ],
        '⚽ Спорт' => [
            'Футбол' => 'sports_soccer',
            'Баскетбол' => 'sports_basketball',
            'Теніс' => 'sports_tennis',
            'Волейбол' => 'sports_volleyball',
            'Хокей' => 'sports_hockey',
            'Біг' => 'directions_run',
            'Тренажери' => 'fitness_center',
            'Трофей' => 'emoji_events',
        ],
        '💻 Технології' => [
            'Технології' => 'devices',
            'Програмування' => 'code',
            'Комп\'ютер' => 'computer',
            'Смартфон' => 'smartphone',
            'AI' => 'smart_toy',
            'Хмара' => 'cloud',
            'Wi-Fi' => 'wifi',
            'Bluetooth' => 'bluetooth',
        ],
        '🏛️ Політика' => [
            'Політика' => 'account_balance',
            'Уряд' => 'gavel',
            'Вибори' => 'how_to_vote',
        ],
        '📈 Економіка та бізнес' => [
            'Економіка' => 'show_chart',
            'Бізнес' => 'business_center',
            'Фінанси' => 'account_balance_wallet',
            'Гроші' => 'attach_money',
            'Інвестиції' => 'trending_up',
            'Криптовалюта' => 'currency_bitcoin',
        ],
        '🎨 Культура та мистецтво' => [
            'Культура' => 'palette',
            'Мистецтво' => 'brush',
            'Музика' => 'music_note',
            'Кіно' => 'movie',
            'Театр' => 'theater_comedy',
            'Книги' => 'menu_book',
            'Музей' => 'museum',
        ],
        '🔬 Наука' => [
            'Наука' => 'science',
            'Лабораторія' => 'biotech',
            'Дослідження' => 'experiment',
            'Космос' => 'rocket_launch',
        ],
        '❤️ Здоров\'я' => [
            'Здоров\'я' => 'health_and_safety',
            'Медицина' => 'medical_services',
            'Лікарня' => 'local_hospital',
            'Спорт' => 'favorite',
            'Фітнес' => 'monitor_heart',
        ],
        '✈️ Подорожі' => [
            'Подорожі' => 'flight',
            'Авто' => 'directions_car',
            'Готель' => 'hotel',
            'Ресторан' => 'restaurant',
            'Карта' => 'map',
            'Визначні місця' => 'tour',
        ],
        '🎓 Освіта' => [
            'Освіта' => 'school',
            'Навчання' => 'cast_for_education',
            'Книги' => 'auto_stories',
            'Диплом' => 'workspace_premium',
        ],
        '🎮 Ігри' => [
            'Ігри' => 'sports_esports',
            'Геймпад' => 'videogame_asset',
            'VR' => 'view_in_ar',
        ],
        '🏠 Інше' => [
            'Головна' => 'home',
            'Популярне' => 'local_fire_department',
            'Тренди' => 'trending_up',
            'Нове' => 'fiber_new',
            'Обране' => 'star',
            'Контакти' => 'contacts',
            'Про нас' => 'info',
            'Допомога' => 'help',
            'Налаштування' => 'settings',
            'Пошук' => 'search',
            'Календар' => 'calendar_month',
            'Коментарі' => 'chat',
            'Поділитися' => 'share',
            'Завантажити' => 'download',
            'Кошик' => 'shopping_cart',
            'Дзвінок' => 'call',
            'Email' => 'email',
            'Локація' => 'location_on',
            'Час' => 'schedule',
            'Замок' => 'lock',
            'Безпека' => 'security',
            'Користувач' => 'person',
            'Група' => 'groups',
            'Адмін' => 'admin_panel_settings',
            'Статистика' => 'analytics',
            'Сповіщення' => 'notifications',
            'Погода' => 'wb_sunny',
            'Рецепти' => 'restaurant_menu',
            'Тварини' => 'pets',
            'Природа' => 'park',
            'Екологія' => 'eco',
            'Енергія' => 'bolt',
            'Будівництво' => 'construction',
            'Дизайн' => 'design_services',
            'Маркетинг' => 'campaign',
            'SEO' => 'query_stats',
                ],
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
            ])  // ← Тут не вистачало закриття дужки
            ->add('type', ChoiceType::class, [
                'label' => 'Тип контенту',
                'choices' => [
                    'Всі типи' => Category::TYPE_ALL,
                    'Статті' => Category::TYPE_ARTICLE,
                    'Блоги' => Category::TYPE_BLOG,
                    'Відео' => Category::TYPE_VIDEO,
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('parent', EntityType::class, [
                'label' => 'Батьківська категорія',
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => '-- Коренева категорія --',
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'query_builder' => function (CategoryRepository $repo) {
                    return $repo->createQueryBuilder('c')
                        ->orderBy('c.sortOrder', 'ASC')
                        ->addOrderBy('c.name', 'ASC');
                },
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Порядок сортування',
                'required' => false,
                'attr' => [
                    'min' => 0,
                    'class' => 'form-input',
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Активна',
                'required' => false,
            ])
            ->add('isVisible', CheckboxType::class, [
                'label' => 'Видима в меню',
                'required' => false,
            ])
            // SEO поля
            ->add('metaTitle', TextType::class, [
                'label' => 'Meta Title',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('metaDescription', TextareaType::class, [
                'label' => 'Meta Description',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'class' => 'form-textarea',
                ],
            ])
            ->add('metaKeywords', TextType::class, [
                'label' => 'Meta Keywords',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}