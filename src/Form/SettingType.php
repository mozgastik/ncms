<?php

namespace App\Form;

use App\Entity\Admin\Setting;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Поля для конфігурації налаштування (всі)
        $builder
            ->add('settingKey', TextType::class, [
                'label' => 'Ключ',
                'required' => true,
                'attr' => [
                    'placeholder' => 'site_name, items_per_page...',
                    'class' => 'form-control',
                ],
                'help' => 'Тільки маленькі літери, цифри та підкреслення',
            ])
            ->add('label', TextType::class, [
                'label' => 'Назва',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Назва сайту, Елементів на сторінці...',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Тип',
                'required' => true,
                'choices' => [
                    'Текст' => 'text',
                    'Текстова область' => 'textarea',
                    'Так/Ні' => 'boolean',
                    'Ціле число' => 'integer',
                    'Десяткове число' => 'float',
                    'Email' => 'email',
                    'URL' => 'url',
                    'Вибір зі списку' => 'choice',
                    'Колір' => 'color',
                    'Дата' => 'date',
                    'JSON' => 'json',
                    'Масив' => 'array',
                ],
            ])
            ->add('settingGroup', TextType::class, [
                'label' => 'Група',
                'required' => true,
                'attr' => [
                    'placeholder' => 'general, appearance, social...',
                ],
                'help' => 'Група для групування налаштувань',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Опис',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Короткий опис що це налаштування робить...',
                ],
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Порядок сортування',
                'required' => false,
                'attr' => [
                    'min' => 0,
                ],
            ])
            ->add('placeholder', TextType::class, [
                'label' => 'Плейсхолдер',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Підказка для поля вводу...',
                ],
            ])
            ->add('icon', TextType::class, [
                'label' => 'Іконка',
                'required' => false,
                'attr' => [
                    'placeholder' => 'fas fa-globe, fas fa-cog...',
                ],
            ])
            ->add('minValue', IntegerType::class, [
                'label' => 'Мінімальне значення',
                'required' => false,
            ])
            ->add('maxValue', IntegerType::class, [
                'label' => 'Максимальне значення',
                'required' => false,
            ])
            ->add('maxLength', IntegerType::class, [
                'label' => 'Максимальна довжина',
                'required' => false,
            ])
            ->add('isRequired', CheckboxType::class, [
                'label' => 'Обов\'язкове',
                'required' => false,
            ])
            ->add('isPublic', CheckboxType::class, [
                'label' => 'Публічне (доступне через API)',
                'required' => false,
            ])
            ->add('isVisible', CheckboxType::class, [
                'label' => 'Видиме',
                'required' => false,
            ])
            ->add('isReadonly', CheckboxType::class, [
                'label' => 'Тільки читання',
                'required' => false,
            ])
            ->add('isSystem', CheckboxType::class, [
                'label' => 'Системне',
                'required' => false,
                'help' => 'Системні налаштування не можна видаляти',
            ])
        ;

        // Динамічне додавання поля value (для значення)
$builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
    /** @var Setting $setting */
    $setting = $event->getData();
    $form = $event->getForm();

    if (!$setting) {
        return;
    }

    // Додаємо поле value для ВСІХ налаштувань (і нових, і існуючих)
    $fieldOptions = [
        'label' => 'Значення',
        'required' => $setting->isRequired(),
        'help' => $setting->getDescription(),
        'attr' => [
            'placeholder' => $setting->getPlaceholder(),
            'data-setting' => $setting->getSettingKey(),
            'data-type' => $setting->getType(),
            'class' => 'setting-field',
        ],
    ];

    if ($setting->getMaxLength()) {
        $fieldOptions['attr']['maxlength'] = $setting->getMaxLength();
    }

    // Динамічне створення поля відповідно до типу
    match ($setting->getType()) {
        'email' => $form->add('value', EmailType::class, $fieldOptions),
        'url' => $form->add('value', UrlType::class, $fieldOptions),
        'integer' => $form->add('value', IntegerType::class, array_merge($fieldOptions, [
            'attr' => [
                'min' => $setting->getMinValue(),
                'max' => $setting->getMaxValue(),
            ]
        ])),
        'float' => $form->add('value', NumberType::class, array_merge($fieldOptions, [
            'attr' => [
                'min' => $setting->getMinValue(),
                'max' => $setting->getMaxValue(),
                'step' => 'any',
            ]
        ])),
        'boolean' => $form->add('value', CheckboxType::class, [
            'label' => 'Значення',
            'required' => false,
        ]),
        'textarea' => $form->add('value', TextareaType::class, array_merge($fieldOptions, [
            'attr' => ['rows' => 5],
        ])),
        'color' => $form->add('value', ColorType::class, $fieldOptions),
        'editor' => $form->add('value', TextareaType::class, array_merge($fieldOptions, [
            'attr' => ['class' => 'tinymce', 'rows' => 10],
        ])),
        'code' => $form->add('value', TextareaType::class, array_merge($fieldOptions, [
            'attr' => ['class' => 'code-editor', 'rows' => 10],
        ])),
        'choice' => $this->addChoiceField($form, $setting, $fieldOptions),
        default => $form->add('value', TextType::class, $fieldOptions),
    };
});
    }

    private function addChoiceField($form, Setting $setting, array $options): void
    {
        $choices = [];
        foreach ($setting->getChoices() as $choice) {
            $choices[$choice] = $choice;
        }

        $form->add('value', ChoiceType::class, array_merge($options, [
            'choices' => $choices,
            'placeholder' => $setting->getPlaceholder() ?? '-- Оберіть --',
            'multiple' => false,
            'expanded' => false,
        ]));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Setting::class,
        ]);
    }
}