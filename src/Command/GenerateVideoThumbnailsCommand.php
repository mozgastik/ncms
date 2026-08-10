<?php
// src/Command/GenerateVideoThumbnailsCommand.php

namespace App\Command;

use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:video:generate-thumbnails',
    description: 'Generate thumbnails for videos without them',
)]
class GenerateVideoThumbnailsCommand extends Command
{
    public function __construct(
        private readonly VideoRepository $videoRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Generating video thumbnails');

        $videos = $this->videoRepository->findBy(['thumbnail' => null]);
        
        if (empty($videos)) {
            $io->success('All videos have thumbnails!');
            return Command::SUCCESS;
        }

        $io->progressStart(count($videos));

        foreach ($videos as $video) {
            $metadata = $this->fetchVideoMetadata($video);
            
            if (!empty($metadata['thumbnail'])) {
                $video->setThumbnail($metadata['thumbnail']);
            }
            
            if (!empty($metadata['duration'])) {
                $video->setDuration($metadata['duration']);
            }
            
            $this->entityManager->flush();
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success(sprintf('Generated thumbnails for %d videos', count($videos)));

        return Command::SUCCESS;
    }

    private function fetchVideoMetadata($video): array
    {
        // Аналогічно VideoService::fetchVideoMetadata
        // ...
    }
}