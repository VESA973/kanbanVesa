<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProjectRole;
use App\Repository\ProjectMemberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectMemberRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_PROJECT_MEMBER', fields: ['project', 'user'])]
class ProjectMember
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
        private Project $project,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(length: 20, enumType: ProjectRole::class)]
        private ProjectRole $role,
        /** Given by the program of the project: follows the program membership, not managed here. */
        #[ORM\Column(options: ['default' => false])]
        private bool $inherited = false,
    ) {
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Compares ids as well as instances: the same user may be loaded by
     * another entity manager (e.g. after a kernel reboot in tests).
     */
    public function isFor(User $user): bool
    {
        return $this->user === $user || (null !== $user->getId() && $this->user->getId() === $user->getId());
    }

    public function getRole(): ProjectRole
    {
        return $this->role;
    }

    public function isInherited(): bool
    {
        return $this->inherited;
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
