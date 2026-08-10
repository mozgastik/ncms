<?php
// src/Form/QuoteType.php

namespace App\Form;

use App\Entity\System\Quote;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;

class QuoteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'Текст цитати',
                'attr' => [
                    'rows' => 6,
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent',
                    'placeholder' => 'Введіть текст цитати...'
                ],
            ])
            ->add('author', TextType::class, [
                'label' => 'Автор',
                'attr' => [
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent',
                    'placeholder' => 'Введіть ім\'я автора'
                ],
            ])
            ->add('source', TextType::class, [
                'label' => 'Джерело',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent',
                    'placeholder' => 'Книга, фільм, виступ тощо (необов\'язково)'
                ],
            ])
            ->add('category', TextType::class, [
                'label' => 'Категорія',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent',
                    'placeholder' => 'Мотивація, філософія, гумористичні тощо (необов\'язково)'
                ],
            ])
            ->add('displayDate', DateType::class, [
                'label' => 'Дата показу',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent',
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Активна',
                'required' => false,
                'attr' => [
                    'class' => 'h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Quote::class,
        ]);
    }
}