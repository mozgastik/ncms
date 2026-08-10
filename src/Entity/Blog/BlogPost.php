<?php

namespace App\Entity\Blog;

use App\Entity\User\User;
use App\Entity\Blog\BlogComment;
use App\Entity\Article\Category;
use App\Entity\Admin\Tag;
use App\Entity\System\Image;
use App\Entity\Article\Like;


use App\Repository\BlogPostRepository;
use App\Repository\LikeRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: BlogPostRepository::class)]
#[ORM\HasLifecycleCallbacks]
class BlogPost
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $excerpt = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_DRAFT;

    #[ORM\Column]
    private int $viewCount = 0;

    #[ORM\Column]
    private int $readingTime = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $moderatorNotes = null;

    #[ORM\Column]
    private bool $isFeatured = false;

    #[ORM\Column]
    private bool $isBreaking = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /**
     * @var Collection<int, BlogComment>
     */
    #[ORM\OneToMany(mappedBy: 'blogPost', targetEntity: BlogComment::class, orphanRemoval: true)]
    private Collection $comments;

    /**
     * @var Collection<int, BlogVote>
     */
    #[ORM\OneToMany(mappedBy: 'blogPost', targetEntity: BlogVote::class, orphanRemoval: true)]
    private Collection $votes;

    /**
     * @var Collection<int, BlogShare>
     */
    #[ORM\OneToMany(mappedBy: 'blogPost', targetEntity: BlogShare::class, orphanRemoval: true)]
    private Collection $shares;

    /**
     * @var Collection<int, BlogBookmark>
     */
    #[ORM\OneToMany(mappedBy: 'blogPost', targetEntity: BlogBookmark::class, orphanRemoval: true)]
    private Collection $bookmarks;

    /**
     * @var Collection<int, BlogModerationLog>
     */
    #[ORM\OneToMany(mappedBy: 'blogPost', targetEntity: BlogModerationLog::class, orphanRemoval: true)]
    private Collection $moderationLogs;

   
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'blogPosts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;
    
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'blogPosts')]
    #[ORM\JoinTable(name: 'blog_post_tag')]
    private Collection $tags;

    #[ORM\OneToMany(mappedBy: 'blogPost', targetEntity: BlogImage::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $images; 

    #[ORM\OneToMany(targetEntity: Like::class, mappedBy: 'blogPost')]
    private Collection $likes;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'blogPosts')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->comments = new ArrayCollection();
        $this->votes = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->shares = new ArrayCollection();
        $this->bookmarks = new ArrayCollection();
        $this->moderationLogs = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->images = new ArrayCollection();
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function setExcerpt(?string $excerpt): static
    {
        $this->excerpt = $excerpt;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function setViewCount(int $viewCount): static
    {
        $this->viewCount = $viewCount;
        return $this;
    }

    public function incrementViewCount(): static
    {
        $this->viewCount++;
        return $this;
    }

    // Для сумісності зі старим кодом, якщо ще використовується incrementViews()
    public function incrementViews(): static
    {
        return $this->incrementViewCount();
    }

    public function getReadingTime(): int
    {
        return $this->readingTime;
    }

    public function setReadingTime(int $readingTime): static
    {
        $this->readingTime = $readingTime;
        return $this;
    }

    public function getModeratorNotes(): ?string
    {
        return $this->moderatorNotes;
    }

    public function setModeratorNotes(?string $moderatorNotes): static
    {
        $this->moderatorNotes = $moderatorNotes;
        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;
        return $this;
    }

    public function isBreaking(): bool
    {
        return $this->isBreaking;
    }

    public function setIsBreaking(bool $isBreaking): static
    {
        $this->isBreaking = $isBreaking;
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

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    /**
     * @return Collection<int, BlogComment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(BlogComment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setBlogPost($this);
        }
        return $this;
    }

    public function removeComment(BlogComment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getBlogPost() === $this) {
                $comment->setBlogPost(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, BlogVote>
     */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function addVote(BlogVote $vote): static
    {
        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $vote->setBlogPost($this);
        }
        return $this;
    }

    public function removeVote(BlogVote $vote): static
    {
        if ($this->votes->removeElement($vote)) {
            if ($vote->getBlogPost() === $this) {
                $vote->setBlogPost(null);
            }
        }
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

    // Додано методи для сумісності з шаблонами
    public function getLikeCount(): int
    {
        return $this->likes->filter(fn($like) => $like->isLike())->count();
    }

    public function getDislikeCount(): int
    {
        return $this->likes->filter(fn($like) => $like->isDislike())->count();
    }

    /**
     * @return Collection<int, BlogShare>
     */
    public function getShares(): Collection
    {
        return $this->shares;
    }

    public function addShare(BlogShare $share): static
    {
        if (!$this->shares->contains($share)) {
            $this->shares->add($share);
            $share->setBlogPost($this);
        }
        return $this;
    }

    public function removeShare(BlogShare $share): static
    {
        if ($this->shares->removeElement($share)) {
            if ($share->getBlogPost() === $this) {
                $share->setBlogPost(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, BlogBookmark>
     */
    public function getBookmarks(): Collection
    {
        return $this->bookmarks;
    }

    public function addBookmark(BlogBookmark $bookmark): static
    {
        if (!$this->bookmarks->contains($bookmark)) {
            $this->bookmarks->add($bookmark);
            $bookmark->setBlogPost($this);
        }
        return $this;
    }

    public function removeBookmark(BlogBookmark $bookmark): static
    {
        if ($this->bookmarks->removeElement($bookmark)) {
            if ($bookmark->getBlogPost() === $this) {
                $bookmark->setBlogPost(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, BlogModerationLog>
     */
    public function getModerationLogs(): Collection
    {
        return $this->moderationLogs;
    }

    public function addModerationLog(BlogModerationLog $moderationLog): static
    {
        if (!$this->moderationLogs->contains($moderationLog)) {
            $this->moderationLogs->add($moderationLog);
            $moderationLog->setBlogPost($this);
        }
        return $this;
    }

    public function removeModerationLog(BlogModerationLog $moderationLog): static
    {
        if ($this->moderationLogs->removeElement($moderationLog)) {
            if ($moderationLog->getBlogPost() === $this) {
                $moderationLog->setBlogPost(null);
            }
        }
        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function getCategory(): ?Category
   {
    return $this->category;
   }

   public function setCategory(?Category $category): self
   {
    $this->category = $category;
    return $this;
   }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            $tag->addBlogPost($this);
        }
        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        if ($this->tags->removeElement($tag)) {
            $tag->removeBlogPost($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, BlogImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(BlogImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setBlogPost($this);
        }
        return $this;
    }

    public function removeImage(BlogImage $image): static
    {
        if ($this->images->removeElement($image)) {
            if ($image->getBlogPost() === $this) {
                $image->setBlogPost(null);
            }
        }
        return $this;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED 
            && $this->publishedAt !== null 
            && $this->publishedAt <= new \DateTimeImmutable();
    }

    public function getFeaturedImage(): ?BlogImage
    {
        return $this->images->first() ?: null;
    }
}