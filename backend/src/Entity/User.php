<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $passwordHash;

    #[ORM\Column(length: 255)]
    private string $masterPasswordHash;

    #[ORM\Column(length: 64)]
    private string $salt;

    #[ORM\Column(type: Types::JSON)]
    private array $kdfParams = [];

    #[ORM\Column(type: Types::JSON)]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(name: 'is_email_verified', type: Types::BOOLEAN)]
    private bool $isEmailVerified = false;

    #[ORM\Column(name: 'is_2fa_enabled', type: Types::BOOLEAN)]
    private bool $is2faEnabled = false;

    #[ORM\Column(name: 'totp_secret', length: 255, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column(name: 'backup_codes', type: Types::JSON, nullable: true)]
    private ?array $backupCodes = null;

    #[ORM\Column(name: 'totp_setup_data', type: Types::JSON, nullable: true)]
    private ?array $totpSetupData = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(targetEntity: Vault::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $vaults;

    #[ORM\OneToMany(targetEntity: RefreshToken::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $refreshTokens;

    #[ORM\Column(name: 'pending_email', length: 255, nullable: true)]
    private ?string $pendingEmail = null;

    #[ORM\Column(name: 'email_change_token_hash', length: 64, nullable: true)]
    private ?string $emailChangeTokenHash = null;

    #[ORM\Column(name: 'email_change_token_expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $emailChangeTokenExpiresAt = null;

    #[ORM\Column(name: 'verification_token_hash', length: 64, nullable: true)]
    private ?string $verificationTokenHash = null;

    #[ORM\Column(name: 'verification_token_expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $verificationTokenExpiresAt = null;

    public function __construct()
    {
        $this->vaults = new ArrayCollection();
        $this->refreshTokens = new ArrayCollection();
    }

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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function getMasterPasswordHash(): string
    {
        return $this->masterPasswordHash;
    }

    public function setMasterPasswordHash(string $masterPasswordHash): self
    {
        $this->masterPasswordHash = $masterPasswordHash;
        return $this;
    }

    public function getSalt(): string
    {
        return $this->salt;
    }

    public function setSalt(string $salt): self
    {
        $this->salt = $salt;
        return $this;
    }

    public function getKdfParams(): array
    {
        return $this->kdfParams;
    }

    public function setKdfParams(array $kdfParams): self
    {
        $this->kdfParams = $kdfParams;
        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isEmailVerified(): bool
    {
        return $this->isEmailVerified;
    }

    public function setIsEmailVerified(bool $isEmailVerified): self
    {
        $this->isEmailVerified = $isEmailVerified;
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

    public function getPendingEmail(): ?string
    {
        return $this->pendingEmail;
    }

    public function setPendingEmail(?string $pendingEmail): self
    {
        $this->pendingEmail = $pendingEmail;
        return $this;
    }

    public function getEmailChangeTokenHash(): ?string
    {
        return $this->emailChangeTokenHash;
    }

    public function setEmailChangeTokenHash(?string $emailChangeTokenHash): self
    {
        $this->emailChangeTokenHash = $emailChangeTokenHash;
        return $this;
    }

    public function getEmailChangeTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->emailChangeTokenExpiresAt;
    }

    public function setEmailChangeTokenExpiresAt(?\DateTimeImmutable $emailChangeTokenExpiresAt): self
    {
        $this->emailChangeTokenExpiresAt = $emailChangeTokenExpiresAt;
        return $this;
    }

    public function getVerificationTokenHash(): ?string
    {
        return $this->verificationTokenHash;
    }

    public function setVerificationTokenHash(?string $verificationTokenHash): self
    {
        $this->verificationTokenHash = $verificationTokenHash;
        return $this;
    }

    public function getVerificationTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->verificationTokenExpiresAt;
    }

    public function setVerificationTokenExpiresAt(?\DateTimeImmutable $verificationTokenExpiresAt): self
    {
        $this->verificationTokenExpiresAt = $verificationTokenExpiresAt;
        return $this;
    }

    public function getVaults(): Collection
    {
        return $this->vaults;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
    }

    public function is2faEnabled(): bool
    {
        return $this->is2faEnabled;
    }

    public function setIs2faEnabled(bool $is2faEnabled): self
    {
        $this->is2faEnabled = $is2faEnabled;
        return $this;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): self
    {
        $this->totpSecret = $totpSecret;
        return $this;
    }

    public function getBackupCodes(): ?array
    {
        return $this->backupCodes;
    }

    public function setBackupCodes(?array $backupCodes): self
    {
        $this->backupCodes = $backupCodes;
        return $this;
    }

    public function getTotpSetupData(): ?array
    {
        return $this->totpSetupData;
    }

    public function setTotpSetupData(?array $totpSetupData): self
    {
        $this->totpSetupData = $totpSetupData;
        return $this;
    }
}
