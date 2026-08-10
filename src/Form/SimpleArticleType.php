<?php

namespace App\Form;

use App\Entity\Article\Article;
use App\Entity\Article\Category;
use App\Entity\Admin\Tag;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SimpleArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Заголовок',
            ])
            ->add('slug', TextType::class, [
                'label' => 'URL-адреса (slug)',
                'required' => false,
                'help' => 'Залиште порожнім для автоматичного створення',
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Контент',
                'attr' => ['rows' => 10],
            ])
            ->add('excerpt', TextareaType::class, [
                'label' => 'Короткий опис',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('coverImage', UrlType::class, [
                'label' => 'Зображення (URL)',
                'required' => false,
            ])
            ->add('publishedAt', DateTimeType::class, [
                'label' => 'Дата публікації',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'Опубліковано',
                'required' => false,
            ])
            ->add('category', EntityType::class, [  // Додано категорію
                'label' => 'Категорія',
                'class' => Category::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Оберіть категорію',
            ]);
            // Теги видалено для простоти
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
        ]);
    }
}