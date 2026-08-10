<?php

namespace App\DataFixtures;

use App\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TagFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $tags = [
            ['name' => 'Новина', 'slug' => 'novyna'],
            ['name' => 'Аналіз', 'slug' => 'analiz'],
            ['name' => 'Ексклюзив', 'slug' => 'eksklyuzyv'],
            ['name' => 'Інтерв\'ю', 'slug' => 'intervyu'],
            ['name' => 'Репортаж', 'slug' => 'reportazh'],
        ];
        
        foreach ($tags as $tagData) {
            $tag = new Tag();
            $tag->setName($tagData['name']);
            $tag->setSlug($tagData['slug']);
            $tag->setCreatedAt(new \DateTime());
            $manager->persist($tag);
            $this->addReference('tag_' . $tagData['slug'], $tag);
        }
        
        $manager->flush();
    }
}