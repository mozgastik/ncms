<?php
// src/Form/VideoType.php

namespace App\Form;

use App\Entity\System\Video;
use App\Entity\Article\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class VideoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Назва відео',
                'attr' => [
                    'placeholder' => 'Введіть назву відео',
                    'class' => 'form-input',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Опис',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Опис відео...',
                    'class' => 'form-textarea',
                ],
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL відео',
                'attr' => [
                    'placeholder' => 'https://youtube.com/watch?v=...',
                    'class' => 'form-input',
                ],
                'help' => 'Підтримуються YouTube, Vimeo, Rutube та локальні відео',
            ])
            ->add('source', ChoiceType::class, [
                'label' => 'Джерело',
                'choices' => [
                    'YouTube' => Video::SOURCE_YOUTUBE,
                    'Vimeo' => Video::SOURCE_VIMEO,
                    'Rutube' => Video::SOURCE_RUTUBE,
                    'Локальне' => Video::SOURCE_LOCAL,
                ],
            ])
            ->add('category', EntityType::class, [
                'label' => 'Категорія',
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => '-- Оберіть категорію --',
                'required' => false,
            ])
            ->add('tags', TextType::class, [
                'label' => 'Теги',
                'required' => false,
                'attr' => [
                    'placeholder' => 'відео, новини, спорт (через кому)',
                ],
                'help' => 'Введіть теги через кому',
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'Опубліковано',
                'required' => false,
            ])
            ->add('isFeatured', CheckboxType::class, [
                'label' => 'Рекомендоване',
                'required' => false,
            ])
            ->add('allowComments', CheckboxType::class, [
                'label' => 'Дозволити коментарі',
                'required' => false,
            ])
            ->add('publishedAt', DateTimeType::class, [
                'label' => 'Дата публікації',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('language', ChoiceType::class, [
                'label' => 'Мова',
                'choices' => [
                    'Українська' => 'uk',
                    'Російська' => 'ru',
                    'Англійська' => 'en',
                ],
            ]);

        // Автоматичне заповнення метаданих при зміні URL
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $video = $event->getData();
            if ($video instanceof Video && $video->getUrl()) {
                $video->parseVideoUrl();
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Video::class,
        ]);
    }
}