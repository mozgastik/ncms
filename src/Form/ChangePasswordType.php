<?php
// src/Form/ChangePasswordType.php

namespace App\Form;

use App\Entity\User\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Поточний пароль',
                'mapped' => false,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Введіть поточний пароль'),
                    new UserPassword(message: 'Невірний поточний пароль'),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Новий пароль',
                    'attr' => ['class' => 'form-control'],
                ],
                'second_options' => [
                    'label' => 'Повторіть новий пароль',
                    'attr' => ['class' => 'form-control'],
                ],
                'invalid_message' => 'Паролі повинні збігатися',
                'constraints' => [
                    new Assert\NotBlank(message: 'Введіть новий пароль'),
                    new Assert\Length(
                        min: 6,
                        minMessage: 'Пароль повинен містити щонайменше {{ limit }} символів'
                    ),
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