<?php
// src/Entity/Video.php

namespace App\Entity\System;

use App\Entity\User\User;
use App\Entity\Blog\BlogPost;
use App\Entity\Article\Article;
use App\Entity\Article\Category;
use App\Repository\VideoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VideoRepository::class)]
#[ORM\Table(name: 'videos')]
#[ORM\HasLifecycleCallbacks]
class Video
{
    public const SOURCE_YOUTUBE = 'youtube';
    public const SOURCE_VIMEO = 'vimeo';
    public const SOURCE_LOCAL = 'local';
    public const SOURCE_RUTUBE = 'rutube';
    public const SOURCE_TELEGRAM = 'telegram';
    public const SOURCE_FACEBOOK = 'facebook';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Назва відео обов\'язкова')]
    #[Assert\Length(min: 3, max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: [
        self::SOURCE_YOUTUBE, 
        self::SOURCE_VIMEO, 
        self::SOURCE_LOCAL,
        self::SOURCE_RUTUBE,
        self::SOURCE_TELEGRAM,
        self::SOURCE_FACEBOOK
    ])]
    private ?string $source = self::SOURCE_YOUTUBE;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    #[Assert\Url]
    private ?string $url = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $embedUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $videoId = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $thumbnail = null;

    #[ORM\Column(nullable: true)]
    private ?int $duration = null; // в секундах

    #[ORM\Column]
    private ?int $views = 0;

    #[ORM\Column]
    private ?int $likes = 0;

    #[ORM\Column]
    private ?bool $isPublished = false;

    #[ORM\Column]
    private ?bool $isFeatured = false;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'videos')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'videos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $tags = null;

    #[ORM\Column]
    private ?bool $allowComments = true;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $language = 'uk';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = [];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function setInitialValues(): void
    {
        $this->parseVideoUrl();
    }

    /**
     * Парсить URL відео та витягує ID та embed URL
     */
    public function parseVideoUrl(): self
    {
        if (!$this->url) {
            return $this;
        }

        // YouTube
        if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $this->url, $match)) {
            $this->source = self::SOURCE_YOUTUBE;
            $this->videoId = $match[1];
            $this->embedUrl = 'https://www.youtube.com/embed/' . $match[1];
            $this->thumbnail = 'https://img.youtube.com/vi/' . $match[1] . '/maxresdefault.jpg';
        }
        // Vimeo
        elseif (preg_match('/(?:vimeo\.com\/(?:video\/)?)(\d+)/', $this->url, $match)) {
            $this->source = self::SOURCE_VIMEO;
            $this->videoId = $match[1];
            $this->embedUrl = 'https://player.vimeo.com/video/' . $match[1];
        }
        // Rutube
        elseif (preg_match('/(?:rutube\.ru\/video\/)([a-f0-9]+)/', $this->url, $match)) {
            $this->source = self::SOURCE_RUTUBE;
            $this->videoId = $match[1];
            $this->embedUrl = 'https://rutube.ru/play/embed/' . $match[1];
        }
        // Telegram
        elseif (preg_match('/(?:t\.me|telegram\.me)\/([a-zA-Z0-9_]+)/', $this->url, $match)) {
            $this->source = self::SOURCE_TELEGRAM;
            $this->videoId = $match[1];
        }
        // Facebook
        elseif (preg_match('/(?:facebook\.com|fb\.watch)\/(?:[^\/]+\/videos\/|\?v=)?(\d+)/', $this->url, $match)) {
            $this->source = self::SOURCE_FACEBOOK;
            $this->videoId = $match[1];
        }
        // Локальне відео
        else {
            $this->source = self::SOURCE_LOCAL;
        }

        return $this;
    }

    // Геттери та сеттери

    public function getId(): ?int { return $this->id; }
    
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    
    public function getSource(): ?string { return $this->source; }
    public function setSource(string $source): self { $this->source = $source; return $this; }
    
    public function getUrl(): ?string { return $this->url; }
    public function setUrl(?string $url): self { $this->url = $url; return $this; }
    
    public function getEmbedUrl(): ?string { return $this->embedUrl; }
    public function setEmbedUrl(?string $embedUrl): self { $this->embedUrl = $embedUrl; return $this; }
    
    public function getVideoId(): ?string { return $this->videoId; }
    public function setVideoId(?string $videoId): self { $this->videoId = $videoId; return $this; }
    
    public function getThumbnail(): ?string { return $this->thumbnail; }
    public function setThumbnail(?string $thumbnail): self { $this->thumbnail = $thumbnail; return $this; }
    
    public function getDuration(): ?int { return $this->duration; }
    public function setDuration(?int $duration): self { $this->duration = $duration; return $this; }
    
    public function getViews(): ?int { return $this->views; }
    public function setViews(int $views): self { $this->views = $views; return $this; }
    public function incrementViews(): self { $this->views++; return $this; }
    
    public function getLikes(): ?int { return $this->likes; }
    public function setLikes(int $likes): self { $this->likes = $likes; return $this; }
    public function incrementLikes(): self { $this->likes++; return $this; }
    
    public function isPublished(): ?bool { return $this->isPublished; }
    public function setIsPublished(bool $isPublished): self { $this->isPublished = $isPublished; return $this; }
    
    public function isFeatured(): ?bool { return $this->isFeatured; }
    public function setIsFeatured(bool $isFeatured): self { $this->isFeatured = $isFeatured; return $this; }
    
    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): self { $this->category = $category; return $this; }
    
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): self { $this->author = $author; return $this; }
    
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
    
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
    
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self { $this->publishedAt = $publishedAt; return $this; }
    
    public function getTags(): ?string { return $this->tags; }
    public function setTags(?string $tags): self { $this->tags = $tags; return $this; }
    
    public function isAllowComments(): ?bool { return $this->allowComments; }
    public function setAllowComments(bool $allowComments): self { $this->allowComments = $allowComments; return $this; }
    
    public function getLanguage(): ?string { return $this->language; }
    public function setLanguage(?string $language): self { $this->language = $language; return $this; }
    
    public function getMetadata(): ?array { return $this->metadata; }
    public function setMetadata(?array $metadata): self { $this->metadata = $metadata; return $this; }

    // Допоміжні методи

    public function getFormattedDuration(): string
    {
        if (!$this->duration) {
            return '00:00';
        }

        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getTagsArray(): array
    {
        return $this->tags ? array_map('trim', explode(',', $this->tags)) : [];
    }

    public function getProviderIcon(): string
    {
        return match ($this->source) {
            self::SOURCE_YOUTUBE => 'fab fa-youtube',
            self::SOURCE_VIMEO => 'fab fa-vimeo-v',
            self::SOURCE_RUTUBE => 'fas fa-play-circle',
            self::SOURCE_TELEGRAM => 'fab fa-telegram',
            self::SOURCE_FACEBOOK => 'fab fa-facebook',
            default => 'fas fa-video',
        };
    }

    public function getProviderColor(): string
    {
        return match ($this->source) {
            self::SOURCE_YOUTUBE => 'text-red-600',
            self::SOURCE_VIMEO => 'text-blue-600',
            self::SOURCE_RUTUBE => 'text-green-600',
            self::SOURCE_TELEGRAM => 'text-blue-400',
            self::SOURCE_FACEBOOK => 'text-blue-600',
            default => 'text-gray-600',
        };
    }
}