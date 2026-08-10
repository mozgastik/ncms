<?php
// src/Form/AdZoneType.php

namespace App\Form;

use App\Entity\Admin\AdZone;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdZoneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Назва'])
            ->add('code', TextType::class, ['label' => 'Код (унікальний)'])
            ->add('width', TextType::class, ['label' => 'Ширина', 'required' => false])
            ->add('height', TextType::class, ['label' => 'Висота', 'required' => false])
            ->add('isActive', null, ['label' => 'Активна'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AdZone::class,
        ]);
    }
}