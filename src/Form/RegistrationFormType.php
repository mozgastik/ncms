<?php

namespace App\Form;

use App\Entity\User\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Ім\'я користувача',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введіть унікальне ім\'я користувача'
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Будь ласка, введіть ім\'я користувача'),
                    // НОВИЙ СИНТАКСИС для Symfony 6+
                    new Assert\Length(
                        min: 3,
                        max: 50,
                        minMessage: 'Ім\'я користувача має містити щонайменше {{ limit }} символи',
                        maxMessage: 'Ім\'я користувача не може перевищувати {{ limit }} символів'
                    ),
                    new Assert\Regex(
                        pattern: '/^[a-zA-Z0-9_]+$/',
                        message: 'Ім\'я користувача може містити тільки латинські літери, цифри та символ підкреслення'
                    )
                ],
            ])
            ->add('mail', EmailType::class, [
                'label' => 'Email адреса',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введіть вашу email адресу'
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Будь ласка, введіть email адресу'),
                    new Assert\Email(message: 'Введіть коректну email адресу'),
                    // НОВИЙ СИНТАКСИС
                    new Assert\Length(
                        max: 180,
                        maxMessage: 'Email не може перевищувати {{ limit }} символів'
                    )
                ],
            ])
            ->add('fullName', TextType::class, [
                'label' => 'Повне ім\'я',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Введіть ваше повне ім\'я (необов\'язково)'
                ],
                'constraints' => [
                    // НОВИЙ СИНТАКСИС
                    new Assert\Length(
                        max: 255,
                        maxMessage: 'Повне ім\'я не може перевищувати {{ limit }} символів'
                    )
                ],
            ])
            ->add('birthDate', DateType::class, [
                'label' => 'Дата народження',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'html5' => true,
                'constraints' => [
                    // НОВИЙ СИНТАКСИС
                    new Assert\Range(
                        max: 'today',
                        maxMessage: 'Дата народження не може бути в майбутньому'
                    )
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Паролі повинні збігатися.',
                'options' => [
                    'attr' => [
                        'class' => 'form-control password-field',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'required' => true,
                'first_options' => [
                    'label' => 'Пароль',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Введіть пароль'
                    ],
                ],
                'second_options' => [
                    'label' => 'Повторіть пароль',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Повторіть пароль'
                    ],
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Будь ласка, введіть пароль'),
                    // НОВИЙ СИНТАКСИС
                    new Assert\Length(
                        min: 6,
                        max: 4096,
                        minMessage: 'Пароль має містити щонайменше {{ limit }} символів'
                    ),
                    new Assert\Regex(
                        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                        message: 'Пароль повинен містити принаймні одну велику літеру, одну малу літеру та одну цифру'
                    )
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'Я погоджуюсь з умовами використання та політикою конфіденційності',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'constraints' => [
                    new Assert\IsTrue(message: 'Ви повинні погодитися з умовами.'),
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