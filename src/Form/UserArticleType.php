<?php
// src/Form/UserArticleType.php

namespace App\Form;

use App\Entity\Article\Article;
use App\Entity\Article\Category;
use App\Entity\Admin\Tag;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;

class UserArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Заголовок',
                'attr' => ['placeholder' => 'Введіть заголовок статті...'],
            ])
            ->add('slug', TextType::class, [
                'label' => 'URL (необов\'язково)',
                'required' => false,
                'attr' => ['placeholder' => 'custom-url'],
            ])
            ->add('excerpt', TextareaType::class, [
                'label' => 'Короткий опис',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Короткий опис для превью...'],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Текст статті',
                'required' => true,
                'attr' => ['class' => 'hidden', 'id' => 'user_article_content'],
            ])
            ->add('category', EntityType::class, [
                'label' => 'Категорія',
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Виберіть категорію',
                'required' => true,
            ])
            ->add('tags', EntityType::class, [
                'label' => 'Теги',
                'class' => Tag::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'attr' => ['class' => 'select2'],
            ])
            // ============================================
            // ПОЛЕ ДЛЯ ЗАВАНТАЖЕННЯ ОБКЛАДИНКИ (ВИПРАВЛЕНО)
            // ============================================
            ->add('coverImageFile', FileType::class, [
                'label' => 'Обкладинка статті',
                'required' => false,
                'mapped' => true,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                            'image/svg+xml',
                        ],
                        mimeTypesMessage: 'Будь ласка, завантажте зображення у форматі JPEG, PNG, WEBP, GIF або SVG',
                    )
                ],
                'attr' => [
                    'accept' => 'image/*',
                    'class' => 'block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-gray-700 dark:file:text-blue-400',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'article_form',
        ]);
    }
}