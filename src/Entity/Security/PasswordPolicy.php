<?php

namespace App\Entity\Security;

use App\Repository\Security\PasswordPolicyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PasswordPolicyRepository::class)]
#[ORM\Table(name: 'security_password_policy')]
#[ORM\HasLifecycleCallbacks]
class PasswordPolicy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'integer', options: ['default' => 8])]
    private int $minLength = 8;

    #[ORM\Column(type: 'integer', options: ['default' => 32])]
    private int $maxLength = 32;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $requireUppercase = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $requireLowercase = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $requireNumber = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $requireSpecial = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $forbidUsername = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $forbidCommonPassword = true;

    #[ORM\Column(type: 'integer', options: ['default' => 90])]
    private int $expireDays = 90;

    #[ORM\Column(type: 'integer', options: ['default' => 3])]
    private int $historyLimit = 3;

    #[ORM\Column(type: 'integer', options: ['default' => 5])]
    private int $maxRetry = 5;

    #[ORM\Column(type: 'integer', options: ['default' => 30])]
    private int $lockMinutes = 30;

    #[ORM\Column(type: 'string', length: 255, options: ['default' => 'Welcome@2024'])]
    private string $defaultPassword = 'Welcome@2024';

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $forceResetPasswordOnFirstLogin = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMinLength(): int
    {
        return $this->minLength;
    }

    public function setMinLength(int $minLength): static
    {
        $this->minLength = $minLength;

        return $this;
    }

    public function getMaxLength(): int
    {
        return $this->maxLength;
    }

    public function setMaxLength(int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    public function isRequireUppercase(): bool
    {
        return $this->requireUppercase;
    }

    public function setRequireUppercase(bool $requireUppercase): static
    {
        $this->requireUppercase = $requireUppercase;

        return $this;
    }

    public function isRequireLowercase(): bool
    {
        return $this->requireLowercase;
    }

    public function setRequireLowercase(bool $requireLowercase): static
    {
        $this->requireLowercase = $requireLowercase;

        return $this;
    }

    public function isRequireNumber(): bool
    {
        return $this->requireNumber;
    }

    public function setRequireNumber(bool $requireNumber): static
    {
        $this->requireNumber = $requireNumber;

        return $this;
    }

    public function isRequireSpecial(): bool
    {
        return $this->requireSpecial;
    }

    public function setRequireSpecial(bool $requireSpecial): static
    {
        $this->requireSpecial = $requireSpecial;

        return $this;
    }

    public function isForbidUsername(): bool
    {
        return $this->forbidUsername;
    }

    public function setForbidUsername(bool $forbidUsername): static
    {
        $this->forbidUsername = $forbidUsername;

        return $this;
    }

    public function isForbidCommonPassword(): bool
    {
        return $this->forbidCommonPassword;
    }

    public function setForbidCommonPassword(bool $forbidCommonPassword): static
    {
        $this->forbidCommonPassword = $forbidCommonPassword;

        return $this;
    }

    public function getExpireDays(): int
    {
        return $this->expireDays;
    }

    public function setExpireDays(int $expireDays): static
    {
        $this->expireDays = $expireDays;

        return $this;
    }

    public function getHistoryLimit(): int
    {
        return $this->historyLimit;
    }

    public function setHistoryLimit(int $historyLimit): static
    {
        $this->historyLimit = $historyLimit;

        return $this;
    }

    public function getMaxRetry(): int
    {
        return $this->maxRetry;
    }

    public function setMaxRetry(int $maxRetry): static
    {
        $this->maxRetry = $maxRetry;

        return $this;
    }

    public function getLockMinutes(): int
    {
        return $this->lockMinutes;
    }

    public function setLockMinutes(int $lockMinutes): static
    {
        $this->lockMinutes = $lockMinutes;

        return $this;
    }

    public function getDefaultPassword(): string
    {
        return $this->defaultPassword;
    }

    public function setDefaultPassword(string $defaultPassword): static
    {
        $this->defaultPassword = $defaultPassword;

        return $this;
    }

    public function isForceResetPasswordOnFirstLogin(): bool
    {
        return $this->forceResetPasswordOnFirstLogin;
    }

    public function setForceResetPasswordOnFirstLogin(bool $forceResetPasswordOnFirstLogin): static
    {
        $this->forceResetPasswordOnFirstLogin = $forceResetPasswordOnFirstLogin;

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

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
