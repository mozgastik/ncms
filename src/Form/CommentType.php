<?php

namespace App\Form;

use App\Entity\Article\ArticleComment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;

class CommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Завжди додаємо всі поля
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'Коментар',
                'constraints' => [
                    new NotBlank(message: 'Коментар не може бути порожнім'),
                    new Length(
                        min: 5,
                        minMessage: 'Коментар має містити щонайменше {{ limit }} символів'
                    ),
                ],
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Напишіть ваш коментар тут...',
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent',
                ],
            ])
            ->add('authorName', TextType::class, [
                'label' => 'Ваше ім\'я',
                'required' => !$options['is_authenticated'], // Обов'язкове тільки для гостей
                'constraints' => !$options['is_authenticated'] ? [
                    new NotBlank(message: 'Будь ласка, введіть ваше ім\'я'),
                    new Length(
                        min: 2,
                        max: 100,
                        minMessage: 'Ім\'я має містити щонайменше {{ limit }} символи',
                        maxMessage: 'Ім\'я не може перевищувати {{ limit }} символів'
                    ),
                ] : [],
                'attr' => [
                    'placeholder' => 'Введіть ваше ім\'я',
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent',
                ],
            ])
            ->add('authorEmail', EmailType::class, [
                'label' => 'Ваш email',
                'required' => false,
                'constraints' => [
                    new Email(message: 'Введіть коректний email'),
                ],
                'attr' => [
                    'placeholder' => 'Введіть ваш email (необов\'язково)',
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent',
                ],
            ]);
        
        // Чекбокс тільки для гостей
        if (!$options['is_authenticated']) {
            $builder->add('agreeTerms', CheckboxType::class, [
                'label' => 'Я погоджуюсь з політикою конфіденційності та умовами використання',
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'Ви повинні погодитись з політикою конфіденційності'),
                ],
                'attr' => [
                    'class' => 'h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500'
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ArticleComment::class, // ← ЗМІНЕНО З Comment::class НА ArticleComment::class
            'is_authenticated' => false,
        ]);
    }
}