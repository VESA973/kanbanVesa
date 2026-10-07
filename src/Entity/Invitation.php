<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProjectRole;
use App\Repository\InvitationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Only the SHA-256 hash of the token is stored: a database leak does not allow
 * anyone to accept a pending invitation.
 */
#[ORM\Entity(repositoryClass: InvitationRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_INVITATION_TOKEN', fields: ['tokenHash'])]
#[ORM\Index(name: 'IDX_INVITATION_EMAIL', fields: ['project', 'email'])]
class Invitation
{
    public const string LIFETIME = '+7 days';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** Task assigned to the invitee as soon as they accept (invitation sent from a task). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Task $task = null;

    /**
     * True once the owner copied the link to share it by hand (WhatsApp…): owning the link
     * then no longer proves that the invitee controls the address, so accepting does not
     * verify the account.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $linkShared = false;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'invitations')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Project $project,
        string $email,
        #[ORM\Column(length: 20, enumType: ProjectRole::class)]
        private ProjectRole $role,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $invitedBy,
        string $plainToken,
    ) {
        if (ProjectRole::OWNER === $role) {
            throw new \InvalidArgumentException('Nobody can be invited as owner.');
        }

        $this->email = mb_strtolower(trim($email));
        $this->createdAt = new \DateTimeImmutable();
        $this->renew($role, $plainToken);
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Inviting the same address again replaces the token and restarts the delay.
     */
    public function renew(ProjectRole $role, string $plainToken): void
    {
        $this->role = $role;
        $this->tokenHash = self::hashToken($plainToken);
        $this->expiresAt = new \DateTimeImmutable(self::LIFETIME);
    }

    public function forTask(?Task $task): void
    {
        if (null !== $task && $task->getProject() !== $this->project) {
            throw new \InvalidArgumentException('The task must belong to the invitation project.');
        }

        $this->task = $task;
    }

    public function getTask(): ?Task
    {
        return $this->task;
    }

    /**
     * Gives a new token to share by hand; the link sent by e-mail stops working.
     */
    public function shareLink(string $plainToken): void
    {
        $this->renew($this->role, $plainToken);
        $this->linkShared = true;
    }

    public function isLinkShared(): bool
    {
        return $this->linkShared;
    }

    public function markAsAccepted(): void
    {
        $this->acceptedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRole(): ProjectRole
    {
        return $this->role;
    }

    public function getInvitedBy(): User
    {
        return $this->invitedBy;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isAccepted(): bool
    {
        return null !== $this->acceptedAt;
    }

    public function isPending(): bool
    {
        return !$this->isAccepted() && !$this->isExpired();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
