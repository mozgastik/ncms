<?php

namespace App\Entity\User;

use App\Entity\Article\Article;
use App\Entity\Article\Like;
use App\Entity\Article\ArticleComment;
use App\Entity\Article\Category;
use App\Entity\Notification\UserNotificationSettings;
use App\Entity\Notification\PushSubscription;
use App\Entity\System\Favorite;
use App\Entity\System\Video;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_MAIL', fields: ['mail'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $mail = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $username = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fullName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $birthDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $plainPassword = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $facebookId = null;

    #[ORM\OneToMany(targetEntity: ArticleComment::class, mappedBy: 'user')]
    private Collection $articleComments;
    /**
     * @var Collection<int, Article>
     */
    #[ORM\OneToMany(targetEntity: Article::class, mappedBy: 'author')]
    private Collection $articles;

    /**
     * @var Collection<int, ArticleLike>
     */
    #[ORM\OneToMany(targetEntity: Like::class, mappedBy: 'user')]
    private Collection $likes;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: UserNotificationSettings::class, cascade: ['persist', 'remove'])]
    private ?UserNotificationSettings $notificationSettings = null;
    /**
   * @var Collection<int, PushSubscription>
    */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: PushSubscription::class, orphanRemoval: true)]
    private Collection $pushSubscriptions;

    #[ORM\OneToMany(targetEntity: Favorite::class, mappedBy: 'user')]
    private Collection $favorites;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Video::class)]
    private Collection $videos;

    /**
   * @ORM\ManyToMany(targetEntity=Article::class)
   * @ORM\JoinTable(name="user_favorite_articles")
   */
    private $favoriteArticles;
    
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->roles = ['ROLE_USER'];
        $this->articleComments = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->favorites = new ArrayCollection();
        $this->favoriteArticles = new ArrayCollection();
        $this->favoriteBlogs = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->notificationSettings = new UserNotificationSettings();
        $this->notificationSettings->setUser($this); // ← ініціалізація
        $this->pushSubscriptions = new ArrayCollection();
        $this->videos = new ArrayCollection(); 
    }

     public function getNotificationSettings(): ?UserNotificationSettings
    {
        // Якщо налаштувань немає, створюємо їх
        if (!$this->notificationSettings) {
            $this->notificationSettings = new UserNotificationSettings();
            $this->notificationSettings->setUser($this);
        }
        return $this->notificationSettings;
    }


    public function setNotificationSettings(?UserNotificationSettings $notificationSettings): self
    {
    if ($notificationSettings && $notificationSettings->getUser() !== $this) {
        $notificationSettings->setUser($this);
    }
    $this->notificationSettings = $notificationSettings;
    return $this;
    }  
    
/**
 * @return Collection<int, PushSubscription>
 */
public function getPushSubscriptions(): Collection
{
    return $this->pushSubscriptions;
}

public function addPushSubscription(PushSubscription $pushSubscription): static
{
    if (!$this->pushSubscriptions->contains($pushSubscription)) {
        $this->pushSubscriptions->add($pushSubscription);
        $pushSubscription->setUser($this);
    }

    return $this;
}

public function removePushSubscription(PushSubscription $pushSubscription): static
{
    if ($this->pushSubscriptions->removeElement($pushSubscription)) {
        if ($pushSubscription->getUser() === $this) {
            $pushSubscription->setUser(null);
        }
    }

    return $this;
}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMail(): ?string
    {
        return $this->mail;
    }

    public function setMail(string $mail): static
    {
        $this->mail = $mail;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->mail;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Додати роль користувачу
     */
    public function addRole(string $role): static
    {
        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
            $this->roles = array_unique($this->roles);
        }

        return $this;
    }

    /**
     * Видалити роль у користувача
     */
    public function removeRole(string $role): static
    {
        $key = array_search($role, $this->roles, true);
        
        if ($key !== false) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }

        return $this;
    }

    /**
     * Перевірити, чи має користувач роль
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    // НОВІ МЕТОДИ ДЛЯ МЕНЮ КОРИСТУВАЧА

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): static
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
        return $this;
    }

    public function getBirthDate(): ?\DateTimeInterface
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeInterface $birthDate): static
    {
        $this->birthDate = $birthDate;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }


   public function getFavorites(): Collection
{
    return $this->favorites;
}

public function addFavorite(Favorite $favorite): self
{
    if (!$this->favorites->contains($favorite)) {
        $this->favorites[] = $favorite;
        $favorite->setUser($this);
    }
    return $this;
}

public function removeFavorite(Favorite $favorite): self
{
    if ($this->favorites->removeElement($favorite)) {
        if ($favorite->getUser() === $this) {
            $favorite->setUser(null);
        }
    }
    return $this;
}
   public function getFavoriteArticles(): Collection
{
    return $this->favoriteArticles;
}

public function addFavoriteArticle(Article $article): self
{
    if (!$this->favoriteArticles->contains($article)) {
        $this->favoriteArticles[] = $article;
    }
    return $this;
}

public function removeFavoriteArticle(Article $article): self
{
    $this->favoriteArticles->removeElement($article);
    return $this;
}

public function getFavoriteBlogs(): Collection
{
    return $this->favoriteBlogs;
}

public function addFavoriteBlog(Blog $blog): self
{
    if (!$this->favoriteBlogs->contains($blog)) {
        $this->favoriteBlogs[] = $blog;
    }
    return $this;
}

public function removeFavoriteBlog(Blog $blog): self
{
    $this->favoriteBlogs->removeElement($blog);
    return $this;
}

/**
 * @return Collection<int, ArticleComment>
 */
public function getComments(): Collection
{
    return $this->articleComments; // ← Змініть на articleComments
}

public function addComment(ArticleComment $comment): static
{
    if (!$this->articleComments->contains($comment)) {
        $this->articleComments->add($comment);
        $comment->setUser($this);
    }

    return $this;
}

public function removeComment(ArticleComment $comment): static
{
    if ($this->articleComments->removeElement($comment)) {
        if ($comment->getUser() === $this) {
            $comment->setUser(null);
        }
    }

    return $this;
}

// Також оновіть метод getApprovedComments():
public function getApprovedComments(): Collection
{
    return $this->articleComments->filter(function(ArticleComment $comment) {
        return $comment->isApproved() && !$comment->isSpam();
    });
}

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        $this->plainPassword = null;
    }

    /**
     * Для зворотної сумісності
     */
    public function getEmail(): ?string
    {
        return $this->mail;
    }

    public function setEmail(string $email): static
    {
        $this->mail = $email;
        return $this;
    }

    /**
     * Отримати ім'я для відображення
     */
    public function getDisplayName(): string
    {
        return $this->fullName ?? $this->username ?? $this->mail;
    }

    /**
     * Отримати вік користувача (якщо вказана дата народження)
     */
    public function getAge(): ?int
    {
        if (!$this->birthDate) {
            return null;
        }

        $today = new \DateTime();
        $interval = $today->diff($this->birthDate);
        
        return $interval->y;
    }

    /**
     * Перевірити, чи є користувач адміністратором
     */
    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->getRoles(), true) || 
               in_array('ROLE_SUPER_ADMIN', $this->getRoles(), true);
    }

    /**
     * Для зручності - alias для getMail()
     */
    public function getEmailAddress(): string
    {
        return $this->mail;
    }

    /**
     * Отримати аватар або дефолтний
     */
    public function getAvatarUrl(): string
    {
        if ($this->avatar) {
            return '/uploads/avatars/' . $this->avatar;
        }
        
        // Gravatar або дефолтний аватар
        $hash = md5(strtolower(trim($this->mail)));
        return "https://www.gravatar.com/avatar/{$hash}?d=mp&s=200";
    }

    /**
     * Повертає ім'я користувача для Twig
     */
    public function __toString(): string
    {
        return $this->getDisplayName();
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
            $article->setAuthor($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): static
    {
        if ($this->articles->removeElement($article)) {
            if ($article->getAuthor() === $this) {
                $article->setAuthor(null);
            }
        }

        return $this;
    }

public function getCommentCount(): int
{
    return $this->getApprovedComments()->count();
}
    /**
     * @return Collection<int, ArticleLike>
     */
    public function getArticleLikes(): Collection
    {
        return $this->articleLikes;
    }

    // Додайте методи для articleLikes, якщо їх немає
    public function addArticleLike(ArticleLike $articleLike): static
    {
        if (!$this->articleLikes->contains($articleLike)) {
            $this->articleLikes->add($articleLike);
            $articleLike->setUser($this);
        }

        return $this;
    }

    public function removeArticleLike(ArticleLike $articleLike): static
    {
        if ($this->articleLikes->removeElement($articleLike)) {
            if ($articleLike->getUser() === $this) {
                $articleLike->setUser(null);
            }
        }

        return $this;
    }
    /**
     * @return Collection<int, Video>
     */
    public function getVideos(): Collection
    {
        return $this->videos;
    }

    public function addVideo(Video $video): static
    {
        if (!$this->videos->contains($video)) {
            $this->videos->add($video);
            $video->setAuthor($this);
        }
        return $this;
    }

    public function removeVideo(Video $video): static
    {
        if ($this->videos->removeElement($video)) {
            // set the owning side to null (unless already changed)
            if ($video->getAuthor() === $this) {
                $video->setAuthor(null);
            }
        }
        return $this;
    }

    public function getVideosCount(): int
    {
        return $this->videos->count();
    }

    public function getGoogleId(): ?string { return $this->googleId; }
    public function setGoogleId(?string $googleId): static { $this->googleId = $googleId; return $this; }

    public function getFacebookId(): ?string { return $this->facebookId; }
    public function setFacebookId(?string $facebookId): static { $this->facebookId = $facebookId; return $this; }
}
