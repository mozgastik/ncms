<?php

namespace App\Form;

use App\Entity\User\User;
use App\Form\UserNotificationSettingsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Validator\Constraints as Assert;

class UserType extends AbstractType
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
            ->add('roles', ChoiceType::class, [
                'label' => 'Ролі',
                'multiple' => true,
                'expanded' => true,
                'choices' => [
                    'Користувач' => 'ROLE_USER',
                    'Адміністратор' => 'ROLE_ADMIN',
                    'Супер-адмін' => 'ROLE_SUPER_ADMIN',
                ],
                'attr' => ['class' => 'form-check'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Активний',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Паролі повинні збігатися',
                'required' => $options['is_new'],
                'first_options' => [
                    'label' => 'Пароль',
                    'attr' => ['class' => 'form-control'],
                ],
                'second_options' => [
                    'label' => 'Повторіть пароль',
                    'attr' => ['class' => 'form-control'],
                ],
                'constraints' => $options['is_new'] ? [
                    new Assert\NotBlank(message: 'Введіть пароль'),
                    new Assert\Length(
                        min: 6,
                        minMessage: 'Пароль повинен містити щонайменше {{ limit }} символів'
                    ),
                ] : [],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_new' => false,
        ]);
    }
}