<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EmailStatus;
use App\Repository\EmailLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One delivery attempt to the SMTP server, to diagnose e-mails that never arrive.
 * Written by App\Mailer\EmailJournal; read-only afterwards.
 */
#[ORM\Entity(repositoryClass: EmailLogRepository::class, readOnly: true)]
#[ORM\Index(name: 'IDX_EMAIL_LOG_CREATED_AT', fields: ['createdAt'])]
class EmailLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $recipients,
        #[ORM\Column(length: 255)]
        private string $subject,
        #[ORM\Column(length: 20, enumType: EmailStatus::class)]
        private EmailStatus $status,
        /** Message-ID header, also shown by Gmail in "Show original". */
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $messageId,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $error,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipients(): string
    {
        return $this->recipients;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getStatus(): EmailStatus
    {
        return $this->status;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
