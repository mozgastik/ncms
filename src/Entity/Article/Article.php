<?php

namespace App\Entity\Article;

use App\Entity\Article\Category;
use App\Entity\Admin\Tag;      
use App\Entity\User\User;
use App\Entity\Article\Comment;
use App\Entity\Article\ArticleImage;
use App\Entity\System\Image;
use App\Entity\Article\Like;   
use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'articles')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_article_status', columns: ['status'])]
#[ORM\Index(name: 'idx_article_published_at', columns: ['published_at'])]
#[ORM\Index(name: 'idx_article_category', columns: ['category_id'])]
#[ORM\Index(name: 'idx_article_author', columns: ['author_id'])]
#[ORM\Index(name: 'idx_article_slug', columns: ['slug'])]
#[Vich\Uploadable]
class Article
{
    // Константи для статусів
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_REJECTED = 'rejected';
    
    // Константи для пріоритетів
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Заголовок не може бути порожнім')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'Заголовок має містити щонайменше {{ limit }} символів',
        maxMessage: 'Заголовок не може перевищувати {{ limit }} символів'
    )]
    private ?string $title = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Зміст статті не може бути порожнім')]
    #[Assert\Length(
        min: 100,
        minMessage: 'Стаття має містити щонайменше {{ limit }} символів'
    )]
    private ?string $content = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $excerpt = null;

    // ============================================
    // ПОЛЯ ДЛЯ VICH UPLOADER
    // ============================================

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $coverImage = null;

    #[Vich\UploadableField(mapping: 'article_images', fileNameProperty: 'coverImage')]
    #[Assert\Image(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        mimeTypesMessage: 'Будь ласка, завантажте зображення у форматі JPEG, PNG, WEBP або GIF',
        maxSizeMessage: 'Файл занадто великий. Максимальний розмір: 5MB'
    )]
    private ?File $coverImageFile = null;

    // ============================================
    // ІНШІ ПОЛЯ
    // ============================================

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $publishedAt = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => self::STATUS_DRAFT])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: 'string', length: 10, nullable: true, options: ['default' => self::PRIORITY_MEDIUM])]
    private ?string $priority = self::PRIORITY_MEDIUM;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $views = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $readingTime = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $likeCount = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $dislikeCount = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $commentCount = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $shareCount = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isBreaking = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isFeatured = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isPinned = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ["default" => ""])]
    private ?string $metaTitle = '';

    #[ORM\Column(type: 'string', length: 500, nullable: true, options: ["default" => ""])]
    private ?string $metaDescription = '';

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ["default" => ""])]
    private ?string $metaKeywords = '';

    #[ORM\Column(type: 'string', length: 50, nullable: true, options: ["default" => ""])]
    private ?string $source = '';

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ["default" => ""])]
    private ?string $sourceUrl = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $moderatorNotes = null;

    // ============================================
    // ЗВ'ЯЗКИ
    // ============================================

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'moderator_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $moderator = null;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')]
    #[ORM\JoinTable(name: 'article_tags')]
    private Collection $tags;

    #[ORM\OneToMany(targetEntity: ArticleComment::class, mappedBy: 'article', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $articleComments;

    #[ORM\OneToMany(targetEntity: Image::class, mappedBy: 'article', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $images;

    #[ORM\OneToMany(targetEntity: Like::class, mappedBy: 'article', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $likes;

    // ============================================
    // КОНСТРУКТОР
    // ============================================

    public function __construct()
    {
        $this->articleComments = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->status = self::STATUS_DRAFT;
        $this->priority = self::PRIORITY_MEDIUM;
        $this->coverImageFile = null;
    }

    public function __toString(): string
    {
        return $this->title ?? 'Article #' . $this->id;
    }

    // ============================================
    // LIFECYCLE CALLBACKS
    // ============================================

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function calculateReadingTime(): void
    {
        if ($this->content) {
            $wordCount = str_word_count(strip_tags($this->content));
            $this->readingTime = max(1, (int) ceil($wordCount / 200));
        }
    }

    #[ORM\PrePersist]
    public function generateSlug(): void
    {
        if ($this->slug === null && $this->title !== null) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $this->title)));
            $this->slug = $slug . '-' . uniqid();
        }
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateMetaData(): void
    {
        if ($this->metaTitle === null) {
            $this->metaTitle = $this->title;
        }
        
        if ($this->metaDescription === null && $this->excerpt) {
            $this->metaDescription = substr($this->excerpt, 0, 160);
        }
    }

    // ============================================
    // BASIC GETTERS & SETTERS
    // ============================================

    public function getId(): ?int { return $this->id; }
    
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }
    
    public function getContent(): ?string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }
    
    public function getExcerpt(): ?string { return $this->excerpt; }
    public function setExcerpt(?string $excerpt): static { $this->excerpt = $excerpt; return $this; }
    
    public function getPublishedAt(): ?\DateTimeInterface { return $this->publishedAt; }
    public function setPublishedAt(?\DateTimeInterface $publishedAt): static { $this->publishedAt = $publishedAt; return $this; }
    
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static 
    { 
        $this->status = $status; 
        return $this; 
    }
    
    public function getPriority(): ?string { return $this->priority; }
    public function setPriority(?string $priority): static { $this->priority = $priority; return $this; }
    
    public function getViews(): int { return $this->views; }
    public function setViews(int $views): static { $this->views = $views; return $this; }
    public function incrementViews(): static { $this->views++; return $this; }
    
    public function getReadingTime(): int { return $this->readingTime; }
    public function setReadingTime(int $readingTime): static { $this->readingTime = $readingTime; return $this; }
    
    public function getLikeCount(): int { return $this->likeCount; }
    public function setLikeCount(int $likeCount): static { $this->likeCount = $likeCount; return $this; }
    public function incrementLikeCount(): static { $this->likeCount++; return $this; }
    public function decrementLikeCount(): static { if ($this->likeCount > 0) $this->likeCount--; return $this; }
    
    public function getDislikeCount(): int { return $this->dislikeCount; }
    public function setDislikeCount(int $dislikeCount): static { $this->dislikeCount = $dislikeCount; return $this; }
    public function incrementDislikeCount(): static { $this->dislikeCount++; return $this; }
    public function decrementDislikeCount(): static { if ($this->dislikeCount > 0) $this->dislikeCount--; return $this; }
    
    public function getCommentCount(): int { return $this->commentCount; }
    public function setCommentCount(int $commentCount): static { $this->commentCount = $commentCount; return $this; }
    public function incrementCommentCount(): static { $this->commentCount++; return $this; }
    public function decrementCommentCount(): static { if ($this->commentCount > 0) $this->commentCount--; return $this; }
    
    public function getShareCount(): int { return $this->shareCount; }
    public function setShareCount(int $shareCount): static { $this->shareCount = $shareCount; return $this; }
    public function incrementShareCount(): static { $this->shareCount++; return $this; }
    
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }
    
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
    
    public function getDeletedAt(): ?\DateTimeInterface { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeInterface $deletedAt): static { $this->deletedAt = $deletedAt; return $this; }
    public function isDeleted(): bool { return $this->deletedAt !== null; }
    public function softDelete(): static { $this->deletedAt = new \DateTime(); return $this; }
    public function restore(): static { $this->deletedAt = null; return $this; }
    
    public function isBreaking(): bool { return $this->isBreaking; }
    public function setIsBreaking(bool $isBreaking): static { $this->isBreaking = $isBreaking; return $this; }
    
    public function isFeatured(): bool { return $this->isFeatured; }
    public function setIsFeatured(bool $isFeatured): static { $this->isFeatured = $isFeatured; return $this; }
    
    public function isPinned(): bool { return $this->isPinned; }
    public function setIsPinned(bool $isPinned): static { $this->isPinned = $isPinned; return $this; }
    
    public function getMetaTitle(): ?string { return $this->metaTitle; }
    public function setMetaTitle(?string $metaTitle): static { $this->metaTitle = $metaTitle; return $this; }
    
    public function getMetaDescription(): ?string { return $this->metaDescription; }
    public function setMetaDescription(?string $metaDescription): static { $this->metaDescription = $metaDescription; return $this; }
    
    public function getMetaKeywords(): ?string { return $this->metaKeywords; }
    public function setMetaKeywords(?string $metaKeywords): static { $this->metaKeywords = $metaKeywords; return $this; }
    
    public function getSource(): ?string { return $this->source; }
    public function setSource(?string $source): static { $this->source = $source; return $this; }
    
    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $sourceUrl): static { $this->sourceUrl = $sourceUrl; return $this; }
    
    public function getModeratorNotes(): ?string { return $this->moderatorNotes; }
    public function setModeratorNotes(?string $moderatorNotes): static { $this->moderatorNotes = $moderatorNotes; return $this; }
    
    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): static { $this->category = $category; return $this; }
    
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): static { $this->author = $author; return $this; }
    
    public function getModerator(): ?User { return $this->moderator; }
    public function setModerator(?User $moderator): static { $this->moderator = $moderator; return $this; }

    // ============================================
    // VICH UPLOADER GETTERS & SETTERS (ОДИН БЛОК)
    // ============================================

    public function getCoverImageFile(): ?File
    {
        return $this->coverImageFile;
    }

    public function setCoverImageFile(?File $coverImageFile): static
    {
        $this->coverImageFile = $coverImageFile;
        if ($coverImageFile) {
            $this->updatedAt = new \DateTime();
        }
        return $this;
    }

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): static
    {
        $this->coverImage = $coverImage;
        return $this;
    }

    /**
     * Отримати URL обкладинки
     */
    public function getCoverImageUrl(): ?string
    {
        if (!$this->coverImage) {
            return null;
        }
        // Якщо це URL (додано через поле в шаблоні)
        if (filter_var($this->coverImage, FILTER_VALIDATE_URL)) {
            return $this->coverImage;
        }
        // Якщо це ім'я файлу (завантажено через VichUploader)
        return '/uploads/articles/' . $this->coverImage;
    }

    /**
     * Перевірити наявність обкладинки
     */
    public function hasCoverImage(): bool
    {
        return $this->coverImage !== null && !empty($this->coverImage);
    }

    /**
     * Отримати шлях до обкладинки
     */
    public function getCoverImagePath(): ?string
    {
        if (!$this->coverImage) {
            return null;
        }
        return 'uploads/articles/' . $this->coverImage;
    }

    /**
     * Отримати ім'я файлу обкладинки
     */
    public function getCoverImageFilename(): ?string
    {
        return $this->coverImage;
    }

    /**
     * Перевірити чи обкладинка є URL
     */
    public function isCoverImageUrl(): bool
    {
        if (!$this->coverImage) {
            return false;
        }
        return filter_var($this->coverImage, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Отримати розмір обкладинки (якщо файл існує)
     */
    public function getCoverImageSize(): ?int
    {
        if (!$this->coverImage || $this->isCoverImageUrl()) {
            return null;
        }
        
        $projectDir = $this->getProjectDir();
        $filePath = $projectDir . '/public/uploads/articles/' . $this->coverImage;
        
        if (file_exists($filePath)) {
            return filesize($filePath);
        }
        
        return null;
    }

    /**
     * Отримати розмір обкладинки у форматованому вигляді
     */
    public function getCoverImageSizeFormatted(): string
    {
        $size = $this->getCoverImageSize();
        if (!$size) {
            return '0 KB';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        
        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }
        
        return round($size, 2) . ' ' . $units[$index];
    }

    /**
     * Отримати MIME тип обкладинки
     */
    public function getCoverImageMimeType(): ?string
    {
        if (!$this->coverImage || $this->isCoverImageUrl()) {
            return null;
        }
        
        $projectDir = $this->getProjectDir();
        $filePath = $projectDir . '/public/uploads/articles/' . $this->coverImage;
        
        if (file_exists($filePath)) {
            return mime_content_type($filePath);
        }
        
        return null;
    }

    /**
     * Отримати розміри обкладинки
     */
    public function getCoverImageDimensions(): ?array
    {
        if (!$this->coverImage || $this->isCoverImageUrl()) {
            return null;
        }
        
        $projectDir = $this->getProjectDir();
        $filePath = $projectDir . '/public/uploads/articles/' . $this->coverImage;
        
        if (file_exists($filePath)) {
            $imageInfo = getimagesize($filePath);
            if ($imageInfo) {
                return [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1],
                ];
            }
        }
        
        return null;
    }

    /**
     * Отримати ширину обкладинки
     */
    public function getCoverImageWidth(): ?int
    {
        $dimensions = $this->getCoverImageDimensions();
        return $dimensions['width'] ?? null;
    }

    /**
     * Отримати висоту обкладинки
     */
    public function getCoverImageHeight(): ?int
    {
        $dimensions = $this->getCoverImageDimensions();
        return $dimensions['height'] ?? null;
    }

    /**
     * Отримати абсолютний шлях до обкладинки
     */
    public function getCoverImageAbsolutePath(): ?string
    {
        if (!$this->coverImage || $this->isCoverImageUrl()) {
            return null;
        }
        
        $projectDir = $this->getProjectDir();
        return $projectDir . '/public/uploads/articles/' . $this->coverImage;
    }

    /**
     * Перевірити чи існує файл обкладинки
     */
    public function coverImageFileExists(): bool
    {
        if (!$this->coverImage || $this->isCoverImageUrl()) {
            return false;
        }
        
        $filePath = $this->getCoverImageAbsolutePath();
        return $filePath !== null && file_exists($filePath);
    }

    // ============================================
    // TAGS
    // ============================================

    public function getTags(): Collection { return $this->tags; }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            $tag->addArticle($this);
        }
        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        if ($this->tags->removeElement($tag)) {
            $tag->removeArticle($this);
        }
        return $this;
    }

    public function hasTag(Tag $tag): bool
    {
        return $this->tags->contains($tag);
    }

    public function getTagsAsString(): string
    {
        $tags = [];
        foreach ($this->tags as $tag) {
            $tags[] = $tag->getName();
        }
        return implode(', ', $tags);
    }

    public function getTagsArray(): array
    {
        $tags = [];
        foreach ($this->tags as $tag) {
            $tags[] = [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
                'slug' => $tag->getSlug(),
            ];
        }
        return $tags;
    }

    // ============================================
    // COMMENTS
    // ============================================

    public function getArticleComments(): Collection
    {
        return $this->articleComments;
    }

    public function addArticleComment(ArticleComment $comment): static
    {
        if (!$this->articleComments->contains($comment)) {
            $this->articleComments->add($comment);
            $comment->setArticle($this);
            $this->incrementCommentCount();
        }
        return $this;
    }

    public function removeArticleComment(ArticleComment $comment): static
    {
        if ($this->articleComments->removeElement($comment)) {
            if ($comment->getArticle() === $this) {
                $comment->setArticle(null);
                $this->decrementCommentCount();
            }
        }
        return $this;
    }

    public function getApprovedArticleComments(): Collection
    {
        return $this->articleComments->filter(function(ArticleComment $comment) {
            return $comment->isApproved() && !$comment->isSpam();
        });
    }

    public function getApprovedArticleCommentsCount(): int
    {
        return $this->getApprovedArticleComments()->count();
    }

    public function getLatestComments(int $limit = 5): array
    {
        $comments = $this->getApprovedArticleComments()->toArray();
        usort($comments, function(ArticleComment $a, ArticleComment $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });
        return array_slice($comments, 0, $limit);
    }

    // ============================================
    // IMAGES (ГАЛЕРЕЯ)
    // ============================================

    public function getImages(): Collection { return $this->images; }

    public function addImage(Image $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setArticle($this);
        }
        return $this;
    }

    public function removeImage(Image $image): static
    {
        if ($this->images->removeElement($image)) {
            if ($image->getArticle() === $this) {
                $image->setArticle(null);
            }
        }
        return $this;
    }

    public function getFeaturedImage(): ?Image
    {
        $featured = $this->images->filter(function(Image $image) {
            return $image->isFeatured();
        })->first();

        return $featured ?: $this->images->first() ?: null;
    }

    public function getGalleryImages(): Collection
    {
        return $this->images->filter(function(Image $image) {
            return !$image->isFeatured();
        });
    }

    public function hasImages(): bool
    {
        return $this->images->count() > 0;
    }

    public function getImagesCount(): int
    {
        return $this->images->count();
    }

    // ============================================
    // LIKES
    // ============================================

    public function getLikes(): Collection { return $this->likes; }

    public function addLike(Like $like): static
    {
        if (!$this->likes->contains($like)) {
            $this->likes->add($like);
            $like->setArticle($this);
            if ($like->isLike()) {
                $this->incrementLikeCount();
            } else {
                $this->incrementDislikeCount();
            }
        }
        return $this;
    }

    public function removeLike(Like $like): static
    {
        if ($this->likes->removeElement($like)) {
            if ($like->getArticle() === $this) {
                $like->setArticle(null);
                if ($like->isLike()) {
                    $this->decrementLikeCount();
                } else {
                    $this->decrementDislikeCount();
                }
            }
        }
        return $this;
    }

    public function getUserLike(User $user): ?Like
    {
        foreach ($this->likes as $like) {
            if ($like->getUser() === $user) {
                return $like;
            }
        }
        return null;
    }

    public function isLikedByUser(User $user): bool
    {
        foreach ($this->likes as $like) {
            if ($like->getUser() === $user && $like->isLike()) {
                return true;
            }
        }
        return false;
    }

    public function isDislikedByUser(User $user): bool
    {
        foreach ($this->likes as $like) {
            if ($like->getUser() === $user && !$like->isLike()) {
                return true;
            }
        }
        return false;
    }

    public function getTotalVotes(): int
    {
        return $this->likeCount + $this->dislikeCount;
    }

    public function getVoteRatio(): float
    {
        $total = $this->getTotalVotes();
        return $total === 0 ? 0.0 : round($this->likeCount / $total * 100, 1);
    }

    public function getVoteInfo(?\App\Entity\User\User $user): array
    {
        return [
            'likes' => $this->likeCount,
            'dislikes' => $this->dislikeCount,
            'userLiked' => $user ? $this->isLikedByUser($user) : false,
            'userDisliked' => $user ? $this->isDislikedByUser($user) : false,
            'total' => $this->getTotalVotes(),
            'ratio' => $this->getVoteRatio(),
        ];
    }

    // ============================================
    // STATUS METHODS
    // ============================================

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && 
               $this->publishedAt !== null && 
               $this->publishedAt <= new \DateTime();
    }

    public function isDraft(): bool { return $this->status === self::STATUS_DRAFT; }
    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool { return $this->status === self::STATUS_APPROVED; }
    public function isArchived(): bool { return $this->status === self::STATUS_ARCHIVED; }
    public function isRejected(): bool { return $this->status === self::STATUS_REJECTED; }

    public function publish(): void
    {
        $this->status = self::STATUS_PUBLISHED;
        if ($this->publishedAt === null) {
            $this->publishedAt = new \DateTime();
        }
    }

    public function archive(): void { $this->status = self::STATUS_ARCHIVED; }
    public function sendToModeration(): void { $this->status = self::STATUS_PENDING; }
    public function draft(): void { $this->status = self::STATUS_DRAFT; }

    public function reject(?string $notes = null): void
    {
        $this->status = self::STATUS_REJECTED;
        if ($notes) {
            $this->moderatorNotes = $notes;
        }
    }

    public function approve(?User $moderator = null): void
    {
        $this->status = self::STATUS_APPROVED;
        if ($moderator) {
            $this->moderator = $moderator;
        }
    }

    public function publishWithModerator(?User $moderator = null): void
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->publishedAt = new \DateTime();
        if ($moderator) {
            $this->moderator = $moderator;
        }
    }

    public function rejectWithReason(string $reason, ?User $moderator = null): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->moderatorNotes = $reason;
        if ($moderator) {
            $this->moderator = $moderator;
        }
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === self::STATUS_DRAFT && 
               $this->title !== null && 
               $this->content !== null &&
               strlen($this->content) >= 100;
    }

    public function submitForModeration(): static
    {
        if (!$this->canBeSubmitted()) {
            throw new \LogicException('Стаття не може бути відправлена на модерацію');
        }
        $this->sendToModeration();
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->status === self::STATUS_REJECTED ? $this->moderatorNotes : null;
    }

    public function getDisplayStatus(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'Чернетка',
            self::STATUS_PENDING => 'На модерації',
            self::STATUS_APPROVED => 'Схвалено',
            self::STATUS_PUBLISHED => 'Опубліковано',
            self::STATUS_REJECTED => 'Відхилено',
            self::STATUS_ARCHIVED => 'Архівовано',
            default => 'Невідомо',
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_PENDING => 'yellow',
            self::STATUS_APPROVED => 'blue',
            self::STATUS_PUBLISHED => 'green',
            self::STATUS_REJECTED => 'red',
            self::STATUS_ARCHIVED => 'purple',
            default => 'gray',
        };
    }

    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
            self::STATUS_PENDING => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            self::STATUS_APPROVED => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            self::STATUS_PUBLISHED => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            self::STATUS_REJECTED => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
            self::STATUS_ARCHIVED => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
        };
    }

    // ============================================
    // PERMISSION METHODS
    // ============================================

    public function canUserEdit(User $user): bool
    {
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }
        
        if ($this->author && $this->author->getId() === $user->getId()) {
            return $this->status === self::STATUS_DRAFT || 
                   $this->status === self::STATUS_REJECTED;
        }
        
        return false;
    }

    public function canUserDelete(User $user): bool
    {
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }
        
        if ($this->author && $this->author->getId() === $user->getId()) {
            return $this->status !== self::STATUS_PUBLISHED;
        }
        
        return false;
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->canUserEdit($user);
    }

    // ============================================
    // UTILITY METHODS
    // ============================================

    public function getTimeSincePublished(): string
    {
        if (!$this->publishedAt) {
            return 'Не опубліковано';
        }
        
        $now = new \DateTime();
        $interval = $now->diff($this->publishedAt);
        
        if ($interval->y > 0) return $interval->y . ' р. тому';
        if ($interval->m > 0) return $interval->m . ' міс. тому';
        if ($interval->d > 0) return $interval->d . ' дн. тому';
        if ($interval->h > 0) return $interval->h . ' год. тому';
        if ($interval->i > 0) return $interval->i . ' хв. тому';
        return 'щойно';
    }

    public function getUrl(): string
    {
        return '/article/' . $this->getSlug();
    }

    public function getPopularityScore(): float
    {
        $score = ($this->views * 0.3) +
                ($this->likeCount * 0.4) +
                ($this->commentCount * 0.2) +
                ($this->shareCount * 0.1);
        return round($score, 2);
    }

    public function isNew(): bool
    {
        if (!$this->publishedAt) return false;
        $now = new \DateTime();
        $interval = $now->diff($this->publishedAt);
        return $interval->days < 1;
    }

    // ============================================
    // STATIC METHODS
    // ============================================

    public static function getStatuses(): array
    {
        return [
            'Чернетка' => self::STATUS_DRAFT,
            'На модерації' => self::STATUS_PENDING,
            'Схвалено' => self::STATUS_APPROVED,
            'Опубліковано' => self::STATUS_PUBLISHED,
            'Відхилено' => self::STATUS_REJECTED,
            'Архів' => self::STATUS_ARCHIVED,
        ];
    }

    public static function getPriorities(): array
    {
        return [
            'Низький' => self::PRIORITY_LOW,
            'Середній' => self::PRIORITY_MEDIUM,
            'Високий' => self::PRIORITY_HIGH,
        ];
    }

    public static function getPriorityColor(string $priority): string
    {
        return match($priority) {
            self::PRIORITY_HIGH => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
            self::PRIORITY_MEDIUM => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
            self::PRIORITY_LOW => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
        };
    }

    public static function getStatusText(string $status): string
    {
        return match($status) {
            self::STATUS_DRAFT => 'Чернетка',
            self::STATUS_PENDING => 'На модерації',
            self::STATUS_APPROVED => 'Схвалено',
            self::STATUS_PUBLISHED => 'Опубліковано',
            self::STATUS_REJECTED => 'Відхилено',
            self::STATUS_ARCHIVED => 'Архів',
            default => 'Невідомий статус',
        };
    }

    /**
     * Alias for getArticleComments() - для зворотної сумісності
     */
    public function getComments(): Collection
    {
        return $this->getArticleComments();
    }

    /**
     * Спеціальний метод для серіалізації
     * Виключаємо coverImageFile з серіалізації
     */
    public function __sleep(): array
    {
        $properties = array_keys(get_object_vars($this));
        return array_diff($properties, ['coverImageFile']);
    }

    /**
     * Допоміжний метод для отримання директорії проекту
     */
    private function getProjectDir(): string
    {
        // Цей метод потрібно викликати з контролера або через параметр
        // Тимчасове рішення - повертаємо поточну директорію
        return dirname(__DIR__, 3);
    }
}