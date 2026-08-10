<?php
// src/Form/Admin/AdminArticleType.php

namespace App\Form\Admin;

use App\Entity\Article\Article;
use App\Entity\Article\Category;
use App\Entity\Admin\Tag;
use App\Entity\User\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Заголовок',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('slug', TextType::class, [
                'label' => 'URL slug',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Текст статті',
                'attr' => ['class' => 'form-control', 'rows' => 15],
            ])
            ->add('excerpt', TextareaType::class, [
                'label' => 'Короткий опис',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('coverImage', UrlType::class, [
                'label' => 'URL обкладинки',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('publishedAt', DateTimeType::class, [
                'label' => 'Дата публікації',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Статус',
                'choices' => Article::getStatuses(),
                'attr' => ['class' => 'form-select'],
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Пріоритет',
                'choices' => Article::getPriorities(),
                'required' => false,
                'attr' => ['class' => 'form-select'],
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
            ->add('category', EntityType::class, [
                'label' => 'Категорія',
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Виберіть категорію',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('tags', EntityType::class, [
                'label' => 'Теги',
                'class' => Tag::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('author', EntityType::class, [
                'label' => 'Автор',
                'class' => User::class,
                'choice_label' => 'username',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('moderatorNotes', TextareaType::class, [
                'label' => 'Нотатки модератора',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
        ]);
    }
}