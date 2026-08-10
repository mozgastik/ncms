<?php
// src/Form/UserVideoType.php

namespace App\Form;

use App\Entity\System\Video;
use App\Entity\Article\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class UserVideoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('url', UrlType::class, [
                'label' => 'Посилання на відео',
                'attr' => [
                    'placeholder' => 'https://youtube.com/watch?v=... або https://vimeo.com/...',
                    'class' => 'form-input',
                ],
                'constraints' => [
                    new NotBlank(message: 'Введіть посилання на відео'),
                    new Url(message: 'Введіть коректне URL'),
                ],
                'help' => 'Підтримуються YouTube, Vimeo, Rutube',
            ])
            ->add('title', TextType::class, [
                'label' => 'Назва відео',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Якщо залишити пустим, назва буде взята з відео',
                    'class' => 'form-input',
                ],
            ])
            ->add('category', EntityType::class, [
                'label' => 'Категорія',
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => '-- Оберіть категорію --',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Опис',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Опишіть ваше відео...',
                    'class' => 'form-textarea',
                ],
            ])
            ->add('tags', TextType::class, [
                'label' => 'Теги',
                'required' => false,
                'attr' => [
                    'placeholder' => 'відео, музика, природа (через кому)',
                    'class' => 'form-input',
                ],
                'help' => 'Теги допоможуть іншим користувачам знайти ваше відео',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Video::class,
        ]);
    }
}