<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\VaultItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: VaultItemRepository::class)]
#[ORM\Table(name: 'vault_items')]
#[ORM\HasLifecycleCallbacks]
class VaultItem
{
    public const TYPE_LOGIN = 'login';
    public const TYPE_NOTE = 'note';
    public const TYPE_CARD = 'card';
    public const TYPE_IDENTITY = 'identity';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Vault::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Vault $vault;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $encryptedData;

    #[ORM\Column(length: 24)]
    private string $iv;

    #[ORM\Column(length: 24)]
    private string $authTag;

    #[ORM\Column(length: 20)]
    private string $itemType;

    #[ORM\Column(length: 255)]
    private string $titleHint;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isFavorite = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getVault(): Vault
    {
        return $this->vault;
    }

    public function setVault(Vault $vault): self
    {
        $this->vault = $vault;
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

    public function getEncryptedData(): string
    {
        return $this->encryptedData;
    }

    public function setEncryptedData(string $encryptedData): self
    {
        $this->encryptedData = $encryptedData;
        return $this;
    }

    public function getIv(): string
    {
        return $this->iv;
    }

    public function setIv(string $iv): self
    {
        $this->iv = $iv;
        return $this;
    }

    public function getAuthTag(): string
    {
        return $this->authTag;
    }

    public function setAuthTag(string $authTag): self
    {
        $this->authTag = $authTag;
        return $this;
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }

    public function setItemType(string $itemType): self
    {
        $this->itemType = $itemType;
        return $this;
    }

    public function getTitleHint(): string
    {
        return $this->titleHint;
    }

    public function setTitleHint(string $titleHint): self
    {
        $this->titleHint = $titleHint;
        return $this;
    }

    public function isFavorite(): bool
    {
        return $this->isFavorite;
    }

    public function setIsFavorite(bool $isFavorite): self
    {
        $this->isFavorite = $isFavorite;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
