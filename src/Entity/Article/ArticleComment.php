<?php

namespace App\Entity\Article;

use App\Entity\User\User;

use App\Repository\ArticleCommentRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: ArticleCommentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ArticleComment
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

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'articleComments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'articleComments')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $authorName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $authorEmail = null;

    #[ORM\OneToMany(targetEntity: Like::class, mappedBy: 'articleComment')]
    private Collection $likes;

     #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $authorIp = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $authorUserAgent = null;
    /**
    * @var Collection<int, ArticleComment>
    */

     #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDeleted = false;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $replies;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->likes = new ArrayCollection();
        $this->replies = new ArrayCollection();
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

    // Базові геттери та сеттери
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

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;
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

    /**
     * @return Collection<int, Like>
     */
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    public function addLike(Like $like): static
    {
        if (!$this->likes->contains($like)) {
            $this->likes->add($like);
            $like->setArticleComment($this);
        }
        return $this;
    }

    public function removeLike(Like $like): static
    {
        if ($this->likes->removeElement($like)) {
            if ($like->getArticleComment() === $this) {
                $like->setArticleComment(null);
            }
        }
        return $this;
    }

    public function getArticleCommentLikeCount(): int
    {
        $count = 0;
        foreach ($this->likes as $like) {
            if ($like->isLike()) {
                $count++;
            }
        }
        return $count;
    }

    public function getArticleCommentDislikeCount(): int
    {
        $count = 0;
        foreach ($this->likes as $like) {
            if ($like->isDislike()) {
                $count++;
            }
        }
        return $count;
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

    public function getArticleCommentRating(): int
    {
        return $this->getArticleCommentLikeCount() - $this->getArticleCommentDislikeCount();
    }

    public function getArticleCommentVoteInfo(?User $user = null): array
    {
        $likeCount = $this->getArticleCommentLikeCount();
        $dislikeCount = $this->getArticleCommentDislikeCount();
        
        $userLiked = false;
        $userDisliked = false;
        
        if ($user) {
            foreach ($this->likes as $like) {
                if ($like->getUser() === $user) {
                    $userLiked = $like->isLike();
                    $userDisliked = $like->isDislike();
                    break;
                }
            }
        }
        
        return [
            'likes' => $likeCount,
            'dislikes' => $dislikeCount,
            'userLiked' => $userLiked,
            'userDisliked' => $userDisliked,
        ];
    }

    public function hasUserVotedOnArticleComment(User $user): bool
    {
        foreach ($this->likes as $like) {
            if ($like->getUser() === $user) {
                return true;
            }
        }
        return false;
    }

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
/**
 * @return Collection<int, ArticleComment>
 */
public function getReplies(): Collection
{
    return $this->replies;
}

public function addReply(ArticleComment $reply): static
{
    if (!$this->replies->contains($reply)) {
        $this->replies->add($reply);
        $reply->setParent($this);
    }
    return $this;
}

public function removeReply(ArticleComment $reply): static
{
    if ($this->replies->removeElement($reply)) {
        if ($reply->getParent() === $this) {
            $reply->setParent(null);
        }
    }
    return $this;
}

public function getParent(): ?self
{
    return $this->parent;
}

public function setParent(?self $parent): static
{
    $this->parent = $parent;
    return $this;
}

/**
 * Перевіряє, чи є коментар відповіддю
 */
public function isReply(): bool
{
    return $this->parent !== null;
}

/**
 * Отримує кількість відповідей
 */
public function getRepliesCount(): int
{
    return $this->replies->count();
}


public function getIsDeleted(): bool
{
    return $this->isDeleted;
}

public function setIsDeleted(bool $isDeleted): self
{
    $this->isDeleted = $isDeleted;
    return $this;
}

public function decrementCommentCount(): self
{
    if ($this->commentCount > 0) {
        $this->commentCount--;
    }
    return $this;
}

}