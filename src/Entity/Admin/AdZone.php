<?php
// src/Entity/AdZone.php

namespace App\Entity\Admin;

use App\Repository\AdZoneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdZoneRepository::class)]
class AdZone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    private ?string $code = null; // унікальний код зони, наприклад 'header', 'sidebar'

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 20)]
    private ?string $width = '100%';

    #[ORM\Column(length: 20)]
    private ?string $height = 'auto';

    #[ORM\OneToMany(mappedBy: 'zone', targetEntity: Ad::class, cascade: ['persist', 'remove'])]
    private Collection $ads;

    #[ORM\Column]
    private ?bool $isActive = true;

    public function __construct()
    {
        $this->ads = new ArrayCollection();
    }

    // Геттери та сеттери ...
    public function getId(): ?int { return $this->id; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getWidth(): ?string { return $this->width; }
    public function setWidth(string $width): static { $this->width = $width; return $this; }
    public function getHeight(): ?string { return $this->height; }
    public function setHeight(string $height): static { $this->height = $height; return $this; }
    /** @return Collection<int, Ad> */
    public function getAds(): Collection { return $this->ads; }
    public function addAd(Ad $ad): static { if (!$this->ads->contains($ad)) { $this->ads->add($ad); $ad->setZone($this); } return $this; }
    public function removeAd(Ad $ad): static { if ($this->ads->removeElement($ad) && $ad->getZone() === $this) { $ad->setZone(null); } return $this; }
    public function isActive(): ?bool { return $this->isActive; }
    public function setActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function setIsActive(bool $isActive): static
   {
    return $this->setActive($isActive);
   }
   }