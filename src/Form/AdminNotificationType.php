<?php

namespace App\Form;

use App\Entity\Notification\AdminNotification;
use App\Entity\User\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminNotificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Заголовок',
                'attr' => [
                    'placeholder' => 'Введіть заголовок сповіщення',
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Повідомлення',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Текст повідомлення...',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Тип сповіщення',
                'choices' => [
                    'Інформація' => AdminNotification::TYPE_INFO,
                    'Успіх' => AdminNotification::TYPE_SUCCESS,
                    'Попередження' => AdminNotification::TYPE_WARNING,
                    'Помилка' => AdminNotification::TYPE_ERROR,
                ],
            ])
            ->add('target', ChoiceType::class, [
                'label' => 'Отримувачі',
                'choices' => [
                    'Усі' => AdminNotification::TARGET_ALL,
                    'Усі користувачі' => AdminNotification::TARGET_USERS,
                    'Адміністратори' => AdminNotification::TARGET_ADMINS,
                    'Конкретний користувач' => AdminNotification::TARGET_SPECIFIC,
                ],
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => static fn (User $user): string => sprintf(
                    '%s (%s)',
                    $user->getDisplayName(),
                    $user->getEmail()
                ),
                'required' => false,
                'placeholder' => 'Оберіть користувача',
                'label' => 'Користувач',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AdminNotification::class,
        ]);
    }
}