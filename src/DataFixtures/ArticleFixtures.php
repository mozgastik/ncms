<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\ArticleComment;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class ArticleFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('uk_UA');
        
        for ($i = 1; $i <= 20; $i++) {
            $article = new Article();
            $article->setTitle($faker->sentence(6));
            $article->setSlug('article-' . $i);
            $article->setContent($faker->text(1000));
            $article->setExcerpt($faker->paragraph());
            $article->setCoverImage('https://picsum.photos/800/400?image=' . $i);
            $article->setPublishedAt($faker->dateTimeThisYear());
            $article->setIsPublished(true);
            $article->setViews($faker->numberBetween(100, 5000));
            $article->setCreatedAt(new \DateTime());
            
            // Добавляем категорию
            $category = $this->getReference('category_' . $faker->randomElement([
                'politika', 'ekonomika', 'tehnologii', 'sport', 'kultura'
            ]));
            $article->setCategory($category);
            
            // Добавляем теги (1-3 случайных тега)
            $tagsCount = $faker->numberBetween(1, 3);
            $usedTags = [];
            for ($j = 0; $j < $tagsCount; $j++) {
                $tagSlug = $faker->randomElement(['novyna', 'analiz', 'eksklyuzyv', 'intervyu', 'reportazh']);
                if (!in_array($tagSlug, $usedTags)) {
                    $tag = $this->getReference('tag_' . $tagSlug);
                    $article->addTag($tag);
                    $usedTags[] = $tagSlug;
                }
            }
            
            // Добавляем комментарии
            $commentsCount = $faker->numberBetween(0, 8);
            for ($k = 0; $k < $commentsCount; $k++) {
                $comment = new Comment();
                $comment->setContent($faker->paragraph());
                $comment->setAuthorName($faker->name());
                $comment->setAuthorEmail($faker->email());
                $comment->setCreatedAt($faker->dateTimeBetween($article->getPublishedAt(), 'now'));
                $comment->setIsApproved($faker->boolean(80));
                $comment->setArticle($article);
                $manager->persist($comment);
            }
            
            $manager->persist($article);
        }
        
        $manager->flush();
    }
    
    public function getDependencies(): array
    {
        return [
            CategoryFixtures::class,
            TagFixtures::class,
        ];
    }
}