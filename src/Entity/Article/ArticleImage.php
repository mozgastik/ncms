<?php

namespace App\Entity\Article;


use App\Entity\User\User;

use App\Repository\ArticleImageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArticleImageRepository::class)]
#[ORM\Table(name: 'article_images')]
#[Vich\Uploadable]
class ArticleImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $imageName = null;

    #[Vich\UploadableField(mapping: 'article_images', fileNameProperty: 'imageName')]
    #[Assert\Image(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
        mimeTypesMessage: 'Будь ласка, завантажте зображення у форматі JPEG, PNG, WEBP, GIF або SVG'
    )]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $uploadedAt = null;

    #[ORM\ManyToOne(inversedBy: 'images')]
    private ?Article $article = null;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    // Гетери та сетери
    public function getId(): ?int { return $this->id; }
    
    public function getImageName(): ?string { return $this->imageName; }
    public function setImageName(?string $imageName): self { $this->imageName = $imageName; return $this; }
    
    public function getImageFile(): ?File { return $this->imageFile; }
    public function setImageFile(?File $imageFile): self 
    { 
        $this->imageFile = $imageFile;
        if ($imageFile) {
            $this->uploadedAt = new \DateTimeImmutable();
        }
        return $this; 
    }
    
    public function getAlt(): ?string { return $this->alt; }
    public function setAlt(?string $alt): self { $this->alt = $alt; return $this; }
    
    public function getUploadedAt(): ?\DateTimeImmutable { return $this->uploadedAt; }
    
    public function getArticle(): ?Article { return $this->article; }
    public function setArticle(?Article $article): self { $this->article = $article; return $this; }

    public function getImageUrl(): string
    {
        return '/uploads/articles/' . $this->imageName;
    }
}