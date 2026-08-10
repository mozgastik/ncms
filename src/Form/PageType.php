<?php
// src/Form/PageType.php

namespace App\Form;

use App\Entity\System\Page;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Назва сторінки',
                'attr' => ['placeholder' => 'Про нас'],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug (URL)',
                'attr' => ['placeholder' => 'about-us'],
                'help' => 'Тільки латинські літери, цифри та дефіси',
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Вміст',
                'required' => false,
                'attr' => ['rows' => 15],
                'help' => 'Можна використовувати HTML',
            ])
            ->add('metaTitle', TextType::class, [
                'label' => 'Meta Title',
                'required' => false,
            ])
            ->add('metaDescription', TextareaType::class, [
                'label' => 'Meta Description',
                'required' => false,
            ])
            ->add('metaKeywords', TextType::class, [
                'label' => 'Meta Keywords',
                'required' => false,
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'Опубліковано',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Page::class,
        ]);
    }
}