<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints as Assert;

class BlogModerationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('action', ChoiceType::class, [
                'label' => 'Рішення',
                'choices' => [
                    'Схвалити та опублікувати' => 'approve',
                    'Відхилити' => 'reject',
                    'Відправити на доопрацювання' => 'revise',
                ],
                'expanded' => true,
                'multiple' => false,
                'attr' => [
                    'class' => 'moderation-actions',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Коментар модератора',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Введіть коментар для автора...',
                    'class' => 'moderation-notes',
                ],
                'constraints' => [
                    new Assert\Length(
                        max: 1000,
                        maxMessage: 'Коментар не може перевищувати {{ limit }} символів'
                    ),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Застосувати рішення',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Без дата-класу, бо це форма для дій
        ]);
    }
}