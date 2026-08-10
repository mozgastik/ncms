<?php

namespace App\Form;

use App\Entity\Article\Article;
use App\Entity\Article\Category;
use App\Entity\Admin\Tag;
use App\Entity\User\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Заголовок',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введіть заголовок статті'
                ],
            ])
            ->add('slug', TextType::class, [
                'label' => 'URL-адреса (slug)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'автоматично-генерується-якщо-порожньо'
                ],
                'help' => 'Залиште порожнім для автоматичного створення',
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Контент',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 15,
                    'placeholder' => 'Текст вашої статті...'
                ],
            ])
            ->add('excerpt', TextareaType::class, [
                'label' => 'Короткий опис',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Короткий опис для превью (необов\'язково)'
                ],
                'help' => 'Якщо залишити порожнім, буде створено автоматично з контенту',
            ])
            ->add('coverImage', UrlType::class, [
                'label' => 'URL обкладинки',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://example.com/image.jpg'
                ],
            ])
            ->add('publishedAt', DateTimeType::class, [
                'label' => 'Дата публікації',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Статус',
                'choices' => [
                    'Чернетка' => Article::STATUS_DRAFT,
                    'На модерації' => Article::STATUS_PENDING,
                    'Схвалено' => Article::STATUS_APPROVED,
                    'Опубліковано' => Article::STATUS_PUBLISHED,
                    'Архів' => Article::STATUS_ARCHIVED,
                    'Відхилено' => Article::STATUS_REJECTED,
                ],
                'attr' => ['class' => 'form-select'],
                'required' => true,
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Пріоритет',
                'choices' => [
                    'Низький' => Article::PRIORITY_LOW,
                    'Середній' => Article::PRIORITY_MEDIUM,
                    'Високий' => Article::PRIORITY_HIGH,
                ],
                'attr' => ['class' => 'form-select'],
                'required' => false,
                'placeholder' => '-- Виберіть пріоритет --',
                'help' => 'Пріоритет визначає важливість статті',
            ])
            ->add('isBreaking', CheckboxType::class, [
                'label' => 'Термінова новина',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('isFeatured', CheckboxType::class, [
                'label' => 'Рекомендована',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('isPinned', CheckboxType::class, [
                'label' => 'Закріплена',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('source', TextType::class, [
                'label' => 'Джерело',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'назва джерела'
                ],
            ])
            ->add('sourceUrl', UrlType::class, [
                'label' => 'URL джерела',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://example.com'
                ],
            ])
            ->add('metaTitle', TextType::class, [
                'label' => 'Meta Title',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Заголовок для SEO'
                ],
            ])
            ->add('metaDescription', TextareaType::class, [
                'label' => 'Meta Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => 'Опис для SEO'
                ],
            ])
            ->add('metaKeywords', TextType::class, [
                'label' => 'Meta Keywords',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ключові, слова, через, кому'
                ],
            ])
            ->add('readingTime', IntegerType::class, [
                'label' => 'Час читання (хвилин)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'placeholder' => 'Автоматично розраховується'
                ],
                'help' => 'Залиште порожнім для автоматичного розрахунку',
            ])
            ->add('moderatorNotes', TextareaType::class, [
                'label' => 'Нотатки модератора',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Причина відхилення або примітки'
                ],
            ])
            ->add('author', EntityType::class, [
                'label' => 'Автор',
                'class' => User::class,
                'choice_label' => 'username',
                'required' => true,
                'placeholder' => '-- Виберіть автора --',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('category', EntityType::class, [
                'label' => 'Категорія',
                'class' => Category::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '-- Без категорії --',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('tags', EntityType::class, [
                'label' => 'Теги',
                'class' => Tag::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-select',
                    'data-placeholder' => 'Виберіть теги'
                ],
            ])
            ->add('moderator', EntityType::class, [
                'label' => 'Модератор',
                'class' => User::class,
                'choice_label' => 'username',
                'required' => false,
                'placeholder' => '-- Виберіть модератора --',
                'attr' => ['class' => 'form-select'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
        ]);
    }
}