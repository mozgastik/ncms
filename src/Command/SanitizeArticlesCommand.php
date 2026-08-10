<?php

namespace App\Command;

use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

#[AsCommand(name: 'app:clean-articles')]
class SanitizeArticlesCommand extends Command
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private EntityManagerInterface $em,
        private HtmlSanitizerInterface $htmlSanitizer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $articles = $this->articleRepository->findAll();
        foreach ($articles as $article) {
            $article->setContent($this->htmlSanitizer->sanitize($article->getContent()));
        }
        $this->em->flush();
        $output->writeln('All articles sanitized.');
        return Command::SUCCESS;
    }
}