<?php

namespace App\Entity\Notification;

use App\Entity\User\User;


use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_notification_settings')]
#[ORM\HasLifecycleCallbacks]
class UserNotificationSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'notificationSettings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    // Email сповіщення
    #[ORM\Column]
    private bool $emailNewArticle = true;

    #[ORM\Column]
    private bool $emailNewComment = true;

    #[ORM\Column]
    private bool $emailCommentReply = true;

    #[ORM\Column]
    private bool $emailWeeklyDigest = false;

    #[ORM\Column]
    private bool $emailNewsletter = false;

    // Push сповіщення
    #[ORM\Column]
    private bool $pushEnabled = false;

    #[ORM\Column]
    private bool $pushNewArticle = true;

    #[ORM\Column]
    private bool $pushNewComment = true;

    #[ORM\Column]
    private bool $pushCommentReply = true;

    // Загальні налаштування
    #[ORM\Column(length: 20)]
    private string $language = 'uk';

    #[ORM\Column]
    private bool $doNotDisturb = false;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $quietHoursStart = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $quietHoursEnd = null;

    #[ORM\Column]
    private bool $marketingAllowed = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
    }

    // Геттери і сеттери...
    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function isEmailNewArticle(): bool { return $this->emailNewArticle; }
    public function setEmailNewArticle(bool $emailNewArticle): self { $this->emailNewArticle = $emailNewArticle; return $this; }

    public function isEmailNewComment(): bool { return $this->emailNewComment; }
    public function setEmailNewComment(bool $emailNewComment): self { $this->emailNewComment = $emailNewComment; return $this; }

    public function isEmailCommentReply(): bool { return $this->emailCommentReply; }
    public function setEmailCommentReply(bool $emailCommentReply): self { $this->emailCommentReply = $emailCommentReply; return $this; }

    public function isEmailWeeklyDigest(): bool { return $this->emailWeeklyDigest; }
    public function setEmailWeeklyDigest(bool $emailWeeklyDigest): self { $this->emailWeeklyDigest = $emailWeeklyDigest; return $this; }

    public function isEmailNewsletter(): bool { return $this->emailNewsletter; }
    public function setEmailNewsletter(bool $emailNewsletter): self { $this->emailNewsletter = $emailNewsletter; return $this; }

    public function isPushEnabled(): bool { return $this->pushEnabled; }
    public function setPushEnabled(bool $pushEnabled): self { $this->pushEnabled = $pushEnabled; return $this; }

    public function isPushNewArticle(): bool { return $this->pushNewArticle; }
    public function setPushNewArticle(bool $pushNewArticle): self { $this->pushNewArticle = $pushNewArticle; return $this; }

    public function isPushNewComment(): bool { return $this->pushNewComment; }
    public function setPushNewComment(bool $pushNewComment): self { $this->pushNewComment = $pushNewComment; return $this; }

    public function isPushCommentReply(): bool { return $this->pushCommentReply; }
    public function setPushCommentReply(bool $pushCommentReply): self { $this->pushCommentReply = $pushCommentReply; return $this; }

    public function getLanguage(): string { return $this->language; }
    public function setLanguage(string $language): self { $this->language = $language; return $this; }

    public function isDoNotDisturb(): bool { return $this->doNotDisturb; }
    public function setDoNotDisturb(bool $doNotDisturb): self { $this->doNotDisturb = $doNotDisturb; return $this; }

    public function getQuietHoursStart(): ?\DateTimeInterface { return $this->quietHoursStart; }
    public function setQuietHoursStart(?\DateTimeInterface $quietHoursStart): self { $this->quietHoursStart = $quietHoursStart; return $this; }

    public function getQuietHoursEnd(): ?\DateTimeInterface { return $this->quietHoursEnd; }
    public function setQuietHoursEnd(?\DateTimeInterface $quietHoursEnd): self { $this->quietHoursEnd = $quietHoursEnd; return $this; }

    public function isMarketingAllowed(): bool { return $this->marketingAllowed; }
    public function setMarketingAllowed(bool $marketingAllowed): self { $this->marketingAllowed = $marketingAllowed; return $this; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // Допоміжні методи
    public function canSendEmail(string $type): bool
    {
        if (!$this->user || !$this->user->getEmail()) {
            return false;
        }

        if ($this->doNotDisturb && $this->isQuietHours()) {
            return false;
        }

        return match($type) {
            'new_article' => $this->emailNewArticle,
            'new_comment' => $this->emailNewComment,
            'comment_reply' => $this->emailCommentReply,
            'weekly_digest' => $this->emailWeeklyDigest,
            'newsletter' => $this->emailNewsletter,
            default => true,
        };
    }


    public function canSendPush(string $type): bool
    {
        if (!$this->pushEnabled) {
            return false;
        }

        if ($this->doNotDisturb && $this->isQuietHours()) {
            return false;
        }

        return match($type) {
            'new_article' => $this->pushNewArticle,
            'new_comment' => $this->pushNewComment,
            'comment_reply' => $this->pushCommentReply,
            default => true,
        };
    }

    private function isQuietHours(): bool
    {
        if (!$this->quietHoursStart || !$this->quietHoursEnd) {
            return false;
        }

        $now = new \DateTime();
        $start = clone $now;
        $end = clone $now;

        $start->setTime(
            (int) $this->quietHoursStart->format('H'),
            (int) $this->quietHoursStart->format('i')
        );
        $end->setTime(
            (int) $this->quietHoursEnd->format('H'),
            (int) $this->quietHoursEnd->format('i')
        );

        if ($start <= $end) {
            return $now >= $start && $now <= $end;
        } else {
            return $now >= $start || $now <= $end;
        }
    }
}