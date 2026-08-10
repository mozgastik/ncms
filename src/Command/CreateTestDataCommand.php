<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:create-test-data',
    description: 'Створює тестові дані (категорію та статтю)',
)]
class CreateTestDataCommand extends Command
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setHelp('Ця команда створює тестові дані для адмін-панелі');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Створення тестових даних...');

        // Перевіряємо чи є вже категорії
        $categories = $this->entityManager->getRepository(Category::class)->findAll();
        
        if (empty($categories)) {
            // Створюємо категорію
            $category = new Category();
            $category->setName('Загальне');
            $category->setSlug('zagalne');
            $category->setDescription('Загальні новини');
            $category->setCreatedAt(new \DateTime());

            $this->entityManager->persist($category);
            $this->entityManager->flush();
            
            $output->writeln('✅ Створено категорію: ' . $category->getName());
        } else {
            $category = $categories[0];
            $output->writeln('✅ Використовуємо існуючу категорію: ' . $category->getName());
        }

        // Перевіряємо чи є вже статті
        $articles = $this->entityManager->getRepository(Article::class)->findAll();
        
        if (empty($articles)) {
            // Створюємо статтю
            $article = new Article();
            $article->setTitle('Перша тестова стаття');
            $article->setSlug('persha-testova-stattya');
            $article->setContent('Це контент першої тестової статті. Тут може бути багато тексту...');
            $article->setExcerpt('Короткий опис першої статті для прев\'ю.');
            $article->setCategory($category);
            $article->setIsPublished(true);
            $article->setViews(0);
            $article->setCoverImage('https://picsum.photos/800/400');
            $article->setPublishedAt(new \DateTime());

            $this->entityManager->persist($article);
            $this->entityManager->flush();
            
            $output->writeln('✅ Створено статтю: ' . $article->getTitle());
        } else {
            $output->writeln('✅ Статті вже існують (' . count($articles) . ' шт.)');
        }

        $output->writeln('');
        $output->writeln('🎉 Тестові дані успішно створені!');
        $output->writeln('📊 Перевірка даних:');
        
        // Показуємо статистику
        $categoryCount = count($this->entityManager->getRepository(Category::class)->findAll());
        $articleCount = count($this->entityManager->getRepository(Article::class)->findAll());
        
        $output->writeln('   Категорій: ' . $categoryCount);
        $output->writeln('   Статей: ' . $articleCount);
        $output->writeln('');
        $output->writeln('🔗 Адмін-панель: /admin');
        $output->writeln('🔗 Список статей: /admin/article');

        return Command::SUCCESS;
    }
}