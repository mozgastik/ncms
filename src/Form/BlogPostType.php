<?php

namespace App\Form;

use App\Entity\Blog\BlogPost;
use App\Entity\Article\Category;
use App\Entity\Admin\Tag;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints as Assert;

class BlogPostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Заголовок',
                'attr' => [
                    'placeholder' => 'Введіть заголовок блогу',
                    'class' => 'form-control-lg',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Заголовок обов\'язковий'),
                    new Assert\Length(
                        min: 5,
                        max: 255,
                        minMessage: 'Заголовок має містити мінімум {{ limit }} символів',
                        maxMessage: 'Заголовок має містити максимум {{ limit }} символів'
                    ),
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Зміст',
                'attr' => [
                    'rows' => 15,
                    'class' => 'editor',
                    'placeholder' => 'Напишіть свій блог тут...',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Зміст обов\'язковий'),
                    new Assert\Length(
                        min: 100,
                        minMessage: 'Зміст має містити мінімум {{ limit }} символів'
                    ),
                ],
            ])
            ->add('excerpt', TextareaType::class, [
                'label' => 'Короткий опис',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Короткий опис, який буде відображатися в списку блогів',
                ],
                'constraints' => [
                    new Assert\Length(
                        max: 500,
                        maxMessage: 'Короткий опис має містити максимум {{ limit }} символів'
                    ),
                ],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'label' => 'Категорія',
                'choice_label' => 'name',
                'placeholder' => 'Виберіть категорію',
                'required' => false,
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('tags', EntityType::class, [
                'class' => Tag::class,
                'label' => 'Теги',
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'attr' => [
                    'class' => 'tags-select',
                    'data-allow-new' => 'true',
                ],
            ])
            ->add('featuredImage', FileType::class, [
                'label' => 'Головне зображення',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'accept' => 'image/*',
                ],
                'constraints' => [
                    new Assert\File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        mimeTypesMessage: 'Будь ласка, завантажте зображення (JPEG, PNG, GIF, WebP)'
                    ),
                ],
            ])
            ->add('additionalImages', CollectionType::class, [
                'entry_type' => FileType::class,
                'entry_options' => [
                    'label' => false,
                    'attr' => ['accept' => 'image/*'],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'required' => false,
                'mapped' => false,
                'label' => 'Додаткові зображення',
                'attr' => [
                    'class' => 'image-collection',
                ],
            ])
            ->add('isBreaking', null, [
                'label' => 'Новина дня',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BlogPost::class,
        ]);
    }
}