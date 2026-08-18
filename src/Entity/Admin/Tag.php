<?php

namespace App\Entity\Admin;

use App\Entity\Article\Article;
use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table(name: 'tags')]
#[ORM\UniqueConstraint(name: 'UNIQ_NAME', columns: ['name'])]
#[ORM\UniqueConstraint(name: 'UNIQ_SLUG', columns: ['slug'])]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 120)]
    private ?string $slug = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $totalUsageCount = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $articleUsageCount = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $blogUsageCount = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $priority = 0;

    /**
     * @var Collection<int, Article>
     */
    #[ORM\ManyToMany(targetEntity: Article::class, mappedBy: 'tags')]
    private Collection $articles;
    
    
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->articles = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getTotalUsageCount(): int
    {
        return $this->totalUsageCount;
    }

    public function getArticleUsageCount(): int
    {
        return $this->articleUsageCount;
    }


    public function incrementArticleUsage(): static
    {
        $this->articleUsageCount++;
        $this->totalUsageCount++;
        return $this;
    }

    public function decrementArticleUsage(): static
    {
        if ($this->articleUsageCount > 0) {
            $this->articleUsageCount--;
            $this->totalUsageCount--;
        }
        return $this;
    }

   

    public function getCreatedAt(): \DateTimeImmutable
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * @return Collection<int, Article>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $this->incrementArticleUsage();
        }

        return $this;
    }

    public function removeArticle(Article $article): static
    {
        if ($this->articles->removeElement($article)) {
            $this->decrementArticleUsage();
        }

        return $this;
    }

    

    /**
     * Отримати загальну кількість публікацій
     */
    public function getTotalPostsCount(): int
    {
        return $this->articles->count() + $this->blogPosts->count();
    }

    /**
     * Отримати всі публікації (статті та блоги)
     */
    public function getAllPosts(): array
    {
        $articles = $this->articles->toArray();
        
        return array_merge($articles);
    }

    /**
     * Отримати публікації відсортовані за датою
     */
    public function getPostsSortedByDate(string $order = 'DESC'): array
    {
        $allPosts = $this->getAllPosts();
        
        usort($allPosts, function($a, $b) use ($order) {
            $dateA = $a->getCreatedAt() ?? $a->getPublishedAt();
            $dateB = $b->getCreatedAt() ?? $b->getPublishedAt();
            
            if ($order === 'DESC') {
                return $dateB <=> $dateA;
            }
            
            return $dateA <=> $dateB;
        });
        
        return $allPosts;
    }

    /**
     * Отримати останні публікації з тегом
     */
    public function getLatestPosts(int $limit = 10): array
    {
        $sortedPosts = $this->getPostsSortedByDate('DESC');
        return array_slice($sortedPosts, 0, $limit);
    }

    /**
     * Перевірити, чи використовується тег
     */
    public function isUsed(): bool
    {
        return $this->totalUsageCount > 0;
    }


    /**
     * Отримати статистику використання тегу
     */
    public function getUsageStatistics(): array
    {
        return [
            'total' => $this->totalUsageCount,
            'articles' => $this->articleUsageCount,
            'is_active' => $this->isActive,
            'primary_type' => $this->getPrimaryContentType(),
        ];
    }
}