<?php

namespace App\Form;

use App\Entity\Blog\BlogCategory;
use App\Repository\BlogCategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BlogCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Назва категорії',
                'attr' => [
                    'placeholder' => 'Введіть назву категорії',
                    'class' => 'w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all',
                    'autocomplete' => 'off'
                ],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug (URL)',
                'required' => true,
                'attr' => [
                    'placeholder' => 'наприклад: novini-ukrainy',
                    'class' => 'w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all font-mono',
                    'autocomplete' => 'off'
                ],
                'help' => 'Використовується в URL. Тільки малі літери, цифри та дефіси.',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Опис категорії',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Введіть опис категорії (необов\'язково)',
                    'rows' => 4,
                    'class' => 'w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all',
                ],
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Порядок сортування',
                'required' => false,
                'attr' => [
                    'placeholder' => '0',
                    'class' => 'w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all',
                    'min' => 0
                ],
                'help' => 'Менше число = вище в списку',
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Активна категорія',
                'required' => false,
                'attr' => [
                    'class' => 'w-5 h-5 rounded border-gray-300 dark:border-gray-700 text-blue-600 focus:ring-blue-500',
                ],
                'label_attr' => [
                    'class' => 'flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer'
                ],
            ])
            ->add('parent', EntityType::class, [
                'class' => BlogCategory::class,
                'label' => 'Батьківська категорія',
                'required' => false,
                'placeholder' => '— Немає батьківської категорії —',
                'choice_label' => 'name',
                'attr' => [
                    'class' => 'w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all',
                ],
                'query_builder' => function (BlogCategoryRepository $repository) use ($options) {
                    $qb = $repository->createQueryBuilder('c')
                        ->orderBy('c.sortOrder', 'ASC')
                        ->addOrderBy('c.name', 'ASC');
                    
                    if (isset($options['data']) && $options['data']->getId()) {
                        $current = $options['data'];
                        $excludeIds = [$current->getId()];
                        
                        foreach ($current->getAllDescendants() as $descendant) {
                            $excludeIds[] = $descendant->getId();
                        }
                        
                        $qb->andWhere('c.id NOT IN (:excludeIds)')
                           ->setParameter('excludeIds', $excludeIds);
                    }
                    
                    return $qb;
                },
                'choice_attr' => function(BlogCategory $category) {
                    $level = $category->getLevel();
                    return [
                        'data-level' => $level,
                        'style' => 'padding-left: ' . ($level * 20) . 'px;'
                    ];
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BlogCategory::class,
        ]);
    }
}