<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $categories = [
            ['name' => 'Політика', 'slug' => 'politika'],
            ['name' => 'Економіка', 'slug' => 'ekonomika'],
            ['name' => 'Технології', 'slug' => 'tehnologii'],
            ['name' => 'Спорт', 'slug' => 'sport'],
            ['name' => 'Культура', 'slug' => 'kultura'],
        ];
        
        foreach ($categories as $categoryData) {
            $category = new Category();
            $category->setName($categoryData['name']);
            $category->setSlug($categoryData['slug']);
            $category->setDescription('Опис категорії ' . $categoryData['name']);
            $category->setCreatedAt(new \DateTime());
            $manager->persist($category);
            $this->addReference('category_' . $categoryData['slug'], $category);
        }
        
        $manager->flush();
    }
}