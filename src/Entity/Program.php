<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProjectColor;
use App\Enum\ProjectRole;
use App\Repository\ProgramRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A "projet global": groups several projects. Its members get the same role
 * on every project it contains (see App\Service\ProgramAccess).
 */
#[ORM\Entity(repositoryClass: ProgramRepository::class)]
class Program
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column(length: 20, enumType: ProjectColor::class)]
    private ProjectColor $color;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, ProgramMember>
     */
    #[ORM\OneToMany(targetEntity: ProgramMember::class, mappedBy: 'program', cascade: ['persist'], orphanRemoval: true)]
    private Collection $members;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'program')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $projects;

    /**
     * @var Collection<int, Invitation>
     */
    #[ORM\OneToMany(targetEntity: Invitation::class, mappedBy: 'program', orphanRemoval: true)]
    private Collection $invitations;

    public function __construct(string $name, User $owner, ProjectColor $color = ProjectColor::INDIGO, ?string $description = null)
    {
        $this->owner = $owner;
        $this->createdAt = new \DateTimeImmutable();
        $this->members = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->invitations = new ArrayCollection();
        $this->update($name, $description, $color);
        $this->addMember($owner, ProjectRole::OWNER);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getColor(): ProjectColor
    {
        return $this->color;
    }

    public function update(string $name, ?string $description, ProjectColor $color): void
    {
        $description = trim((string) $description);
        $this->name = $name;
        $this->description = '' === $description ? null : $description;
        $this->color = $color;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, ProgramMember>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(User $user, ProjectRole $role): ProgramMember
    {
        if (null !== $this->getMemberOf($user)) {
            throw new \LogicException(\sprintf('User "%s" is already a member of this program.', $user->getEmail()));
        }

        $member = new ProgramMember($this, $user, $role);
        $this->members->add($member);

        return $member;
    }

    public function removeMember(ProgramMember $member): void
    {
        if (ProjectRole::OWNER === $member->getRole()) {
            throw new \LogicException('The owner cannot be removed from the program.');
        }

        $this->members->removeElement($member);
    }

    public function getMemberOf(User $user): ?ProgramMember
    {
        foreach ($this->members as $member) {
            if ($member->isFor($user)) {
                return $member;
            }
        }

        return null;
    }

    public function getRoleOf(User $user): ?ProjectRole
    {
        return $this->getMemberOf($user)?->getRole();
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    /**
     * @return Collection<int, Invitation>
     */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }
}
