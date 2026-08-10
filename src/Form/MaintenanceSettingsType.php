<?php
// src/Form/MaintenanceSettingsType.php

namespace App\Form;

use App\Entity\System\MaintenanceSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MaintenanceSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'Увімкнути режим обслуговування',
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'label' => 'Заголовок',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Сайт на технічному обслуговуванні',
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Повідомлення',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Опишіть причину та орієнтовний час завершення робіт',
                ],
            ])
            ->add('startAt', DateTimeType::class, [
                'label' => 'Початок обслуговування',
                'required' => false,
                'widget' => 'single_text',
                'help' => 'Залиште пустим для негайного початку',
            ])
            ->add('endAt', DateTimeType::class, [
                'label' => 'Завершення обслуговування',
                'required' => false,
                'widget' => 'single_text',
                'help' => 'Залиште пустим для ручного вимкнення',
            ])
            ->add('allowAdminAccess', CheckboxType::class, [
                'label' => 'Дозволити доступ адміністраторам',
                'required' => false,
                'help' => 'Адміністратори зможуть бачити сайт навіть в режимі обслуговування',
            ])
            ->add('backgroundColor', ColorType::class, [
                'label' => 'Колір фону',
                'required' => false,
            ])
            ->add('textColor', ColorType::class, [
                'label' => 'Колір тексту',
                'required' => false,
            ])
            ->add('accentColor', ColorType::class, [
                'label' => 'Акцентний колір',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MaintenanceSettings::class,
        ]);
    }
}