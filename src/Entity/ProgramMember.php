<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProjectRole;
use App\Repository\ProgramMemberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgramMemberRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_PROGRAM_MEMBER', fields: ['program', 'user'])]
class ProgramMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $joinedAt;

    /** « Responsable »: a designation shown to everyone, it gives no extra right. */
    #[ORM\Column(name: 'is_lead', options: ['default' => false])]
    private bool $lead = false;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'members')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Program $program,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(length: 20, enumType: ProjectRole::class)]
        private ProjectRole $role,
    ) {
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProgram(): Program
    {
        return $this->program;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Compares ids as well as instances (see ProjectMember::isFor()).
     */
    public function isFor(User $user): bool
    {
        return $this->user === $user || (null !== $user->getId() && $this->user->getId() === $user->getId());
    }

    public function getRole(): ProjectRole
    {
        return $this->role;
    }

    public function changeRole(ProjectRole $role): void
    {
        if (ProjectRole::OWNER === $this->role || ProjectRole::OWNER === $role) {
            throw new \LogicException('Ownership cannot be given or taken through a role change.');
        }

        $this->role = $role;
    }

    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function isLead(): bool
    {
        return $this->lead;
    }

    public function toggleLead(): void
    {
        $this->lead = !$this->lead;
    }
}
