<?php

namespace App\Entity\Blog;

use App\Repository\BlogCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BlogCategoryRepository::class)]
class BlogCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Назва категорії не може бути порожньою')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Назва повинна містити щонайменше {{ limit }} символи',
        maxMessage: 'Назва не може перевищувати {{ limit }} символів'
    )]
    private ?string $name = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Slug не може бути порожнім')]
    #[Assert\Regex(
        pattern: '/^[a-z0-9-]+$/',
        message: 'Slug може містити тільки малі літери, цифри та дефіси'
    )]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'Опис не може перевищувати {{ limit }} символів'
    )]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Порядок сортування не може бути порожнім')]
    #[Assert\PositiveOrZero(message: 'Порядок сортування має бути додатнім числом або нулем')]
    #[Assert\Type(type: 'integer', message: 'Порядок сортування має бути числом')]
    private ?int $sortOrder = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Статус активності не може бути порожнім')]
    #[Assert\Type(type: 'bool', message: 'Статус активності має бути логічним значенням')]
    private ?bool $isActive = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'blogCategories')]
    #[Assert\Expression(
        expression: "value !== this",
        message: 'Категорія не може бути батьківською сама для себе'
    )]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $blogCategories;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: BlogPost::class)]
    private Collection $blogPosts;

    public function __construct()
    {
        $this->blogCategories = new ArrayCollection();
        $this->isActive = true; // За замовчуванням категорія активна
        $this->sortOrder = 0;
        $this->blogPosts = new ArrayCollection();   // За замовчуванням порядок сортування 0
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

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        // Запобігаємо встановленню самої себе як батьківської
        if ($parent === $this) {
            throw new \InvalidArgumentException('Категорія не може бути батьківською сама для себе');
        }
        
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getBlogCategories(): Collection
    {
        return $this->blogCategories;
    }

    public function addBlogCategory(self $blogCategory): static
    {
        if (!$this->blogCategories->contains($blogCategory)) {
            $this->blogCategories->add($blogCategory);
            $blogCategory->setParent($this);
        }

        return $this;
    }

    public function removeBlogCategory(self $blogCategory): static
    {
        if ($this->blogCategories->removeElement($blogCategory)) {
            // set the owning side to null (unless already changed)
            if ($blogCategory->getParent() === $this) {
                $blogCategory->setParent(null);
            }
        }

        return $this;
    }

    /**
     * Повертає повний шлях категорії (для навігації)
     */
    public function getPath(): string
    {
        $path = [$this->getName()];
        $parent = $this->getParent();
        
        while ($parent) {
            array_unshift($path, $parent->getName());
            $parent = $parent->getParent();
        }
        
        return implode(' / ', $path);
    }
    
    public function getBlogPosts(): Collection
    {
    return $this->blogPosts;
     }

    public function addBlogPost(BlogPost $blogPost): static
   {
    if (!$this->blogPosts->contains($blogPost)) {
        $this->blogPosts->add($blogPost);
        $blogPost->setCategory($this);
    }
    return $this;
   }

    public function removeBlogPost(BlogPost $blogPost): static
   {
    if ($this->blogPosts->removeElement($blogPost)) {
        if ($blogPost->getCategory() === $this) {
            $blogPost->setCategory(null);
        }
    }
    return $this;
   }
    /**
     * Повертає кількість підкатегорій
     */
    public function getChildrenCount(): int
    {
        return $this->blogCategories->count();
    }

    /**
     * Перевіряє, чи є категорія батьківською для іншої
     */
    public function isParentOf(self $category): bool
    {
        return $this->blogCategories->contains($category);
    }

    /**
     * Перевіряє, чи є категорія нащадком іншої
     */
    public function isChildOf(self $category): bool
    {
        return $this->getParent() === $category;
    }

    /**
     * Повертає всіх нащадків категорії (рекурсивно)
     */
    public function getAllDescendants(): array
    {
        $descendants = [];
        
        foreach ($this->blogCategories as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $child->getAllDescendants());
        }
        
        return $descendants;
    }

    /**
     * Повертає рівень вкладеності категорії
     */
    public function getLevel(): int
    {
        $level = 0;
        $parent = $this->getParent();
        
        while ($parent) {
            $level++;
            $parent = $parent->getParent();
        }
        
        return $level;
    }

    /**
     * Повертає відформатовану назву з відступами для відображення в дереві
     */
    public function getIndentedName(string $prefix = '—', int $spaces = 2): string
    {
        $level = $this->getLevel();
        $indent = str_repeat(' ', $level * $spaces);
        
        if ($level > 0) {
            $indent .= $prefix . ' ';
        }
        
        return $indent . $this->getName();
    }

    public function __toString(): string
    {
        return $this->getName() ?? 'Нова категорія';
    }
}