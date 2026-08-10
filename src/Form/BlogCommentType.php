<?php
// src/Form/BlogCommentType.php

namespace App\Form;

use App\Entity\Blog\BlogComment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Constraints as Assert;

class BlogCommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isAuthenticated = $options['is_authenticated'] ?? false;
        
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'Ваш коментар',
                'required' => true,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Напишіть ваш коментар тут...',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Коментар не може бути порожнім'
                    ),
                    new Assert\Length(
                        min: 5,
                        max: 2000,
                        minMessage: 'Коментар має містити щонайменше {{ limit }} символів',
                        maxMessage: 'Коментар не може перевищувати {{ limit }} символів'
                    ),
                ],
            ]);
        
        // Якщо користувач не авторизований, додаємо поля для імені та email
        if (!$isAuthenticated) {
            $builder
                ->add('authorName', TextType::class, [
                    'label' => 'Ваше ім\'я',
                    'required' => true,
                    'attr' => [
                        'placeholder' => 'Введіть ваше ім\'я',
                        'class' => 'form-control',
                    ],
                    'constraints' => [
                        new Assert\NotBlank(
                            message: 'Будь ласка, введіть ваше ім\'я'
                        ),
                        new Assert\Length(
                            min: 2,
                            max: 100,
                            minMessage: 'Ім\'я має містити щонайменше {{ limit }} символи',
                            maxMessage: 'Ім\'я не може перевищувати {{ limit }} символів'
                        ),
                    ],
                ])
                ->add('authorEmail', EmailType::class, [
                    'label' => 'Ваш email',
                    'required' => false, // Необов'язково для гостей
                    'attr' => [
                        'placeholder' => 'Введіть ваш email (необов\'язково)',
                        'class' => 'form-control',
                    ],
                    'constraints' => [
                        new Assert\Email(
                            message: 'Введіть коректний email адрес'
                        ),
                        new Assert\Length(
                            max: 180,
                            maxMessage: 'Email не може перевищувати {{ limit }} символів'
                        ),
                    ],
                ]);
        }
        
        // Приховане поле для parent (якщо потрібно для відповідей)
        $builder->add('parent', HiddenType::class, [
            'required' => false,
            'mapped' => true,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BlogComment::class,
            'is_authenticated' => false,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'blog_comment',
        ]);
    }
}