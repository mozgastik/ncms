<?php
// src/Form/ProfileType.php

namespace App\Form;

use App\Entity\User\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints as Assert;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mail', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Email обов\'язковий'),
                    new Assert\Email(message: 'Введіть коректний email'),
                ],
            ])
            ->add('username', TextType::class, [
                'label' => 'Ім\'я користувача',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Ім\'я користувача обов\'язкове'),
                    new Assert\Length(
                        min: 3,
                        max: 50,
                        minMessage: 'Ім\'я користувача має містити щонайменше {{ limit }} символи',
                        maxMessage: 'Ім\'я користувача не може перевищувати {{ limit }} символів'
                    ),
                ],
            ])
            ->add('fullName', TextType::class, [
                'label' => 'Повне ім\'я',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'Про себе',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Розкажіть трохи про себе...'
                ],
            ])
            ->add('birthDate', DateType::class, [
                'label' => 'Дата народження',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('avatarFile', FileType::class, [
                'label' => 'Аватар',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/gif'],
                        mimeTypesMessage: 'Будь ласка, завантажте зображення (JPEG, PNG або GIF)'
                    )
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}