<?php

namespace App\Entity\System;

use App\Entity\Article\Article;
use App\Entity\User\User;

use App\Repository\ImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImageRepository::class)]
class Image
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $url = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alt = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Article $article = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnail = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters and setters...
    public function getId(): ?int { return $this->id; }
    public function getUrl(): ?string { return $this->url; }
    public function setUrl(string $url): static { $this->url = $url; return $this; }
    public function getAlt(): ?string { return $this->alt; }
    public function setAlt(?string $alt): static { $this->alt = $alt; return $this; }
    public function getArticle(): ?Article { return $this->article; }
    public function setArticle(?Article $article): static { $this->article = $article; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUploadedAt(): ?\DateTimeImmutable { return $this->uploadedAt; }
    public function setUploadedAt(\DateTimeImmutable $uploadedAt): self { $this->uploadedAt = $uploadedAt; return $this; }
    public function getThumbnail(): ?string { return $this->thumbnail; }
    public function setThumbnail(?string $thumbnail): self { $this->thumbnail = $thumbnail; return $this; }
    public function getPath(): ?string { return $this->path; }
    public function setPath(string $path): self { $this->path = $path; return $this; }
}