<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SmtpEncryption;
use App\Repository\SmtpSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Outgoing mail server, edited by administrators. A single row (id 1).
 * The password is stored encrypted (see App\Service\SecretBox).
 */
#[ORM\Entity(repositoryClass: SmtpSettingsRepository::class)]
class SmtpSettings
{
    public const int SINGLETON_ID = 1;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::SINGLETON_ID;

    #[ORM\Column(length: 255)]
    private string $host = '';

    #[ORM\Column]
    private int $port = 587;

    #[ORM\Column(length: 10, enumType: SmtpEncryption::class)]
    private SmtpEncryption $encryption = SmtpEncryption::STARTTLS;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $encryptedPassword = null;

    #[ORM\Column(length: 180)]
    private string $fromAddress = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $fromName = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function update(string $host, int $port, SmtpEncryption $encryption, ?string $username, string $fromAddress, ?string $fromName): void
    {
        $this->host = trim($host);
        $this->port = $port;
        $this->encryption = $encryption;
        $this->username = '' === trim((string) $username) ? null : trim((string) $username);
        $this->fromAddress = mb_strtolower(trim($fromAddress));
        $this->fromName = '' === trim((string) $fromName) ? null : trim((string) $fromName);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setEncryptedPassword(?string $encryptedPassword): void
    {
        $this->encryptedPassword = $encryptedPassword;
    }

    public function isConfigured(): bool
    {
        return '' !== $this->host && '' !== $this->fromAddress;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getEncryption(): SmtpEncryption
    {
        return $this->encryption;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getEncryptedPassword(): ?string
    {
        return $this->encryptedPassword;
    }

    public function hasPassword(): bool
    {
        return null !== $this->encryptedPassword;
    }

    public function getFromAddress(): string
    {
        return $this->fromAddress;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
