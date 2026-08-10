<?php


namespace App\Entity\Admin;

use App\Repository\AdRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Ad
{
    public const TYPE_IMAGE = 'image';
    public const TYPE_HTML = 'html';
    public const TYPE_SCRIPT = 'script';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: [self::TYPE_IMAGE, self::TYPE_HTML, self::TYPE_SCRIPT])]
    private ?string $type = self::TYPE_IMAGE;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $code = null; // HTML/JS код для типів html та script

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $image = null; // URL зображення для типу image

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $link = null; // посилання, куди веде банер

    #[ORM\Column(nullable: true)]
    private ?int $priority = 0; // вищий пріоритет — частіше показ

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\ManyToOne(targetEntity: AdZone::class, inversedBy: 'ads')]
    #[ORM\JoinColumn(nullable: false)]
    private ?AdZone $zone = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void { $this->updatedAt = new \DateTimeImmutable(); }

    // Геттери та сеттери ...
    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $code): static { $this->code = $code; return $this; }
    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }
    public function getLink(): ?string { return $this->link; }
    public function setLink(?string $link): static { $this->link = $link; return $this; }
    public function getPriority(): ?int { return $this->priority; }
    public function setPriority(?int $priority): static { $this->priority = $priority; return $this; }
    public function isActive(): ?bool { return $this->isActive; }
    public function setActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getStartAt(): ?\DateTimeImmutable { return $this->startAt; }
    public function setStartAt(?\DateTimeImmutable $startAt): static { $this->startAt = $startAt; return $this; }
    public function getEndAt(): ?\DateTimeImmutable { return $this->endAt; }
    public function setEndAt(?\DateTimeImmutable $endAt): static { $this->endAt = $endAt; return $this; }
    public function getZone(): ?AdZone { return $this->zone; }
    public function setZone(?AdZone $zone): static { $this->zone = $zone; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    // Допоміжний метод для перевірки активності за датами
    public function isCurrentlyActive(): bool
    {
        if (!$this->isActive) return false;
        $now = new \DateTimeImmutable();
        if ($this->startAt && $now < $this->startAt) return false;
        if ($this->endAt && $now > $this->endAt) return false;
        return true;
    }
    public function setIsActive(bool $isActive): static
   {
    return $this->setActive($isActive);
   }
}