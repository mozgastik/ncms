<?php
// src/Form/AdType.php

namespace App\Form;

use App\Entity\Admin\Ad;
use App\Entity\Admin\AdZone;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Назва'])
            ->add('type', ChoiceType::class, [
                'label' => 'Тип',
                'choices' => [
                    'Зображення' => Ad::TYPE_IMAGE,
                    'HTML код' => Ad::TYPE_HTML,
                    'JavaScript' => Ad::TYPE_SCRIPT,
                ]
            ])
            ->add('code', TextareaType::class, ['label' => 'Код (HTML/JS)', 'required' => false])
            ->add('image', UrlType::class, ['label' => 'URL зображення', 'required' => false])
            ->add('link', UrlType::class, ['label' => 'Посилання', 'required' => false])
            ->add('priority', IntegerType::class, ['label' => 'Пріоритет', 'required' => false])
            ->add('isActive', null, ['label' => 'Активний'])
            ->add('startAt', DateTimeType::class, ['label' => 'Початок показу', 'widget' => 'single_text', 'required' => false])
            ->add('endAt', DateTimeType::class, ['label' => 'Кінець показу', 'widget' => 'single_text', 'required' => false])
            ->add('zone', EntityType::class, [
                'class' => AdZone::class,
                'choice_label' => 'name',
                'label' => 'Рекламна зона'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Ad::class]);
    }
}