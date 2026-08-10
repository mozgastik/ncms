<?php
// src/Twig/LikeExtension.php

namespace App\Twig;

use App\Entity\Like;
use App\Repository\LikeRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LikeExtension extends AbstractExtension
{
    public function __construct(
        private LikeRepository $likeRepository,
        private Security $security
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_likes', [$this, 'getLikes']),
            new TwigFunction('get_dislikes', [$this, 'getDislikes']),
            new TwigFunction('get_user_vote', [$this, 'getUserVote']),
            new TwigFunction('is_liked_by_user', [$this, 'isLikedByUser']),
            new TwigFunction('is_disliked_by_user', [$this, 'isDislikedByUser']),
            new TwigFunction('get_vote_url', [$this, 'getVoteUrl']),
        ];
    }

    public function getLikes(string $entityType, int $entityId): int
    {
        return $this->likeRepository->countLikes($entityType, $entityId);
    }

    public function getDislikes(string $entityType, int $entityId): int
    {
        return $this->likeRepository->countDislikes($entityType, $entityId);
    }

    public function getUserVote(string $entityType, int $entityId): ?string
    {
        $user = $this->security->getUser();
        if (!$user) {
            return null;
        }
        
        $vote = $this->likeRepository->findUserVote($user->getId(), $entityType, $entityId);
        
        return $vote?->getVoteType();
    }

    public function isLikedByUser(string $entityType, int $entityId): bool
    {
        return $this->getUserVote($entityType, $entityId) === Like::VOTE_LIKE;
    }

    public function isDislikedByUser(string $entityType, int $entityId): bool
    {
        return $this->getUserVote($entityType, $entityId) === Like::VOTE_DISLIKE;
    }

    public function getVoteUrl(string $entityType, int $entityId, string $voteType): string
    {
        return '/like/' . $entityType . '/' . $entityId . '/' . $voteType;
    }
}