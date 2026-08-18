<?php
// src/Entity/Like.php

namespace App\Entity\Article;

use App\Entity\User\User;
use App\Entity\Article\Article;


use App\Repository\LikeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LikeRepository::class)]
#[ORM\Table(name: 'likes')]
#[ORM\Index(name: 'idx_like_user', columns: ['user_id'])]
#[ORM\UniqueConstraint(name: 'unique_user_article', columns: ['user_id', 'article_id'])]
#[ORM\UniqueConstraint(name: 'unique_user_article_comment', columns: ['user_id', 'article_comment_id'])]
class Like
{
    public const TYPE_ARTICLE = 'article';
    public const TYPE_COMMENT = 'comment';
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ArticleComment::class, inversedBy: 'likes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?ArticleComment $articleComment = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'likes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'likes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;


    #[ORM\Column]
    private bool $isLike = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isLike = true;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function isLike(): bool
    {
        return $this->isLike;
    }

    public function setIsLike(bool $isLike): static
    {
        $this->isLike = $isLike;
        return $this;
    }

    public function isDislike(): bool
    {
        return !$this->isLike;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;
        // Якщо встановлюємо статтю, скидаємо інші сутності
        if ($article !== null) {
            $this->articleComment = null;
        }
        return $this;
    }

    public function getArticleComment(): ?ArticleComment
    {
        return $this->articleComment;
    }

    public function setArticleComment(?ArticleComment $articleComment): static
    {
        $this->articleComment = $articleComment;
        // Якщо встановлюємо коментар до статті, скидаємо інші сутності
        if ($articleComment !== null) {
            $this->article = null;
        }
        return $this;
    }


    // Додаткові методи для визначення типу сутності
    public function getTargetType(): ?string
    {
        if ($this->article !== null) {
            return self::TYPE_ARTICLE;
        }
        if ($this->articleComment !== null) {
            return self::TYPE_COMMENT;
        }
        return null;
    }

    public function getTarget(): ?object
    {
        return match($this->getTargetType()) {
            self::TYPE_ARTICLE => $this->article,
            self::TYPE_COMMENT => $this->articleComment,
            default => null,
        };
    }

    public function getTargetId(): ?int
    {
        $target = $this->getTarget();
        return $target?->getId();
    }
}