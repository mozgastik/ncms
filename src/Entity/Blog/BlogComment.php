<?php
// src/Entity/BlogComment.php

namespace App\Entity\Blog;

use App\Entity\User\User;
use App\Entity\Article\Like;

use App\Repository\BlogCommentRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: BlogCommentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class BlogComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private bool $isApproved = false;

    #[ORM\Column]
    private bool $isSpam = false;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $authorName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $authorEmail = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $authorIp = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $authorUserAgent = null;

    // ✅ ПРАВИЛЬНО: ManyToOne - один BlogPost для багатьох коментарів
    #[ORM\ManyToOne(targetEntity: BlogPost::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?BlogPost $blogPost = null;

    // ✅ ПРАВИЛЬНО: ManyToOne - один User для багатьох коментарів (якщо коментар від авторизованого користувача)
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'blogComments')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    // ✅ OneToMany для відповідей (якщо підтримуєте вкладені коментарі)
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $replies;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?BlogComment $parent = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $depth = 0;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $likeCount = 0;

    #[ORM\OneToMany(targetEntity: Like::class, mappedBy: 'blogComment')]
    private Collection $likes;


    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->replies = new ArrayCollection();
        $this->isApproved = false;
        $this->isSpam = false;
        $this->depth = 0;
        $this->likeCount = 0;
        $this->likes = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isApproved(): bool
    {
        return $this->isApproved;
    }

    public function setIsApproved(bool $isApproved): static
    {
        $this->isApproved = $isApproved;
        return $this;
    }

    public function isSpam(): bool
    {
        return $this->isSpam;
    }

    public function setIsSpam(bool $isSpam): static
    {
        $this->isSpam = $isSpam;
        return $this;
    }

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function setAuthorName(?string $authorName): static
    {
        $this->authorName = $authorName;
        return $this;
    }

    public function getAuthorEmail(): ?string
    {
        return $this->authorEmail;
    }

    public function setAuthorEmail(?string $authorEmail): static
    {
        $this->authorEmail = $authorEmail;
        return $this;
    }

    public function getAuthorIp(): ?string
    {
        return $this->authorIp;
    }

    public function setAuthorIp(?string $authorIp): static
    {
        $this->authorIp = $authorIp;
        return $this;
    }

    public function getAuthorUserAgent(): ?string
    {
        return $this->authorUserAgent;
    }

    public function setAuthorUserAgent(?string $authorUserAgent): static
    {
        $this->authorUserAgent = $authorUserAgent;
        return $this;
    }

    // ✅ ПРАВИЛЬНО: Повертає один BlogPost або null
    public function getBlogPost(): ?BlogPost
    {
        return $this->blogPost;
    }

    // ✅ ПРАВИЛЬНО: Приймає один BlogPost або null
    public function setBlogPost(?BlogPost $blogPost): static
    {
        $this->blogPost = $blogPost;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Collection<int, BlogComment>
     */
    public function getReplies(): Collection
    {
        return $this->replies;
    }

    public function addReply(BlogComment $reply): static
    {
        if (!$this->replies->contains($reply)) {
            $this->replies->add($reply);
            $reply->setParent($this);
        }
        return $this;
    }

    public function removeReply(BlogComment $reply): static
    {
        if ($this->replies->removeElement($reply)) {
            if ($reply->getParent() === $this) {
                $reply->setParent(null);
            }
        }
        return $this;
    }

    public function getParent(): ?BlogComment
    {
        return $this->parent;
    }

    public function setParent(?BlogComment $parent): static
    {
        $this->parent = $parent;
        if ($parent) {
            $this->depth = $parent->getDepth() + 1;
        } else {
            $this->depth = 0;
        }
        return $this;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function setDepth(int $depth): static
    {
        $this->depth = $depth;
        return $this;
    }
public function getLikes(): Collection
{
    return $this->likes;
}

public function addLike(Like $like): static
{
    if (!$this->likes->contains($like)) {
        $this->likes->add($like);
        $like->setBlogPost($this);
    }
    return $this;
}

public function removeLike(Like $like): static
{
    if ($this->likes->removeElement($like)) {
        if ($like->getBlogPost() === $this) {
            $like->setBlogPost(null);
        }
    }
    return $this;
}

// Метод для отримання інформації про голоси (для Twig)
public function getVoteInfo(LikeRepository $likeRepository, ?User $user): array
{
    $likes = $likeRepository->countLikes(Like::TYPE_ARTICLE, $this->getId());
    $dislikes = $likeRepository->countDislikes(Like::TYPE_ARTICLE, $this->getId());
    
    $userLiked = false;
    $userDisliked = false;
    
    if ($user) {
        $userVote = $likeRepository->findUserVote($user->getId(), Like::TYPE_ARTICLE, $this->getId());
        if ($userVote) {
            $userLiked = $userVote->isLike();
            $userDisliked = $userVote->isDislike();
        }
    }
    
    return [
        'likes' => $likes,
        'dislikes' => $dislikes,
        'userLiked' => $userLiked,
        'userDisliked' => $userDisliked,
    ];
}

    public function setLikeCount(int $likeCount): static
    {
        $this->likeCount = $likeCount;
        return $this;
    }

    public function incrementLikeCount(): static
    {
        $this->likeCount++;
        return $this;
    }

    public function decrementLikeCount(): static
    {
        if ($this->likeCount > 0) {
            $this->likeCount--;
        }
        return $this;
    }

    // Допоміжні методи
    public function getDisplayName(): string
    {
        if ($this->user) {
            return $this->user->getDisplayName() ?? $this->user->getUsername();
        }
        return $this->authorName ?? 'Анонім';
    }

    public function isGuestComment(): bool
    {
        return $this->user === null;
    }
}