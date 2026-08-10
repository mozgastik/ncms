<?php
// src/Form/UserNotificationSettingsType.php

namespace App\Form;

use App\Entity\Notification\UserNotificationSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserNotificationSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('language', ChoiceType::class, [
                'label' => 'Мова сповіщень',
                'choices' => [
                    'Українська' => 'uk',
                    'English' => 'en',
                ],
                'expanded' => true,
            ])
        ;

        // Email секція
        $builder
            ->add('emailNewArticle', CheckboxType::class, [
                'label' => 'Нові статті',
                'required' => false,
            ])
            ->add('emailNewComment', CheckboxType::class, [
                'label' => 'Нові коментарі до моїх статей',
                'required' => false,
            ])
            ->add('emailCommentReply', CheckboxType::class, [
                'label' => 'Відповіді на мої коментарі',
                'required' => false,
            ])
            ->add('emailWeeklyDigest', CheckboxType::class, [
                'label' => 'Щотижневий дайджест',
                'required' => false,
            ])
            ->add('emailNewsletter', CheckboxType::class, [
                'label' => 'Новини та спеціальні пропозиції',
                'required' => false,
            ])
        ;

        // Push секція
        $builder
            ->add('pushEnabled', CheckboxType::class, [
                'label' => 'Увімкнути Push-сповіщення',
                'required' => false,
            ])
            ->add('pushNewArticle', CheckboxType::class, [
                'label' => 'Нові статті',
                'required' => false,
            ])
            ->add('pushNewComment', CheckboxType::class, [
                'label' => 'Нові коментарі',
                'required' => false,
            ])
            ->add('pushCommentReply', CheckboxType::class, [
                'label' => 'Відповіді',
                'required' => false,
            ])
        ;

        // Розширені налаштування
        $builder
            ->add('doNotDisturb', CheckboxType::class, [
                'label' => 'Не турбувати',
                'required' => false,
                'help' => 'Не надсилати сповіщення у вказаний час',
            ])
            ->add('quietHoursStart', TimeType::class, [
                'label' => 'Початок',
                'widget' => 'single_text',
                'required' => false,
                'html5' => true,
            ])
            ->add('quietHoursEnd', TimeType::class, [
                'label' => 'Кінець',
                'widget' => 'single_text',
                'required' => false,
                'html5' => true,
            ])
            ->add('marketingAllowed', CheckboxType::class, [
                'label' => 'Отримувати маркетингові матеріали',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserNotificationSettings::class,
        ]);
    }
}