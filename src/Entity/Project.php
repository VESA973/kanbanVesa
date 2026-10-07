<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProjectColor;
use App\Enum\ProjectRole;
use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
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

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, ProjectMember>
     */
    #[ORM\OneToMany(targetEntity: ProjectMember::class, mappedBy: 'project', cascade: ['persist'], orphanRemoval: true)]
    private Collection $members;

    /**
     * @var Collection<int, BoardColumn>
     */
    #[ORM\OneToMany(targetEntity: BoardColumn::class, mappedBy: 'project', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $columns;

    /**
     * @var Collection<int, Invitation>
     */
    #[ORM\OneToMany(targetEntity: Invitation::class, mappedBy: 'project', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $invitations;

    /**
     * @var Collection<int, Label>
     */
    #[ORM\OneToMany(targetEntity: Label::class, mappedBy: 'project', orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $labels;

    public function __construct(string $name, User $owner, ProjectColor $color = ProjectColor::INDIGO, ?string $description = null)
    {
        $this->name = $name;
        $this->owner = $owner;
        $this->color = $color;
        $this->description = $this->normalizeDescription($description);
        $this->createdAt = new \DateTimeImmutable();
        $this->members = new ArrayCollection();
        $this->columns = new ArrayCollection();
        $this->invitations = new ArrayCollection();
        $this->labels = new ArrayCollection();
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
        $this->name = $name;
        $this->description = $this->normalizeDescription($description);
        $this->color = $color;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, ProjectMember>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(User $user, ProjectRole $role): ProjectMember
    {
        if (null !== $this->getRoleOf($user)) {
            throw new \LogicException(\sprintf('User "%s" is already a member of this project.', $user->getEmail()));
        }

        $member = new ProjectMember($this, $user, $role);
        $this->members->add($member);

        return $member;
    }

    /**
     * @return Collection<int, BoardColumn>
     */
    public function getColumns(): Collection
    {
        return $this->columns;
    }

    public function addColumn(string $name): BoardColumn
    {
        $column = new BoardColumn($this, $name, $this->columns->count());
        $this->columns->add($column);

        return $column;
    }

    /**
     * @return Collection<int, Invitation>
     */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    /**
     * @return Collection<int, Label>
     */
    public function getLabels(): Collection
    {
        return $this->labels;
    }

    public function getMemberOf(User $user): ?ProjectMember
    {
        foreach ($this->members as $member) {
            if ($member->isFor($user)) {
                return $member;
            }
        }

        return null;
    }

    public function removeMember(ProjectMember $member): void
    {
        if (ProjectRole::OWNER === $member->getRole()) {
            throw new \LogicException('The owner cannot be removed from the project.');
        }

        $this->members->removeElement($member);
    }

    public function getRoleOf(User $user): ?ProjectRole
    {
        return $this->getMemberOf($user)?->getRole();
    }

    private function normalizeDescription(?string $description): ?string
    {
        $description = trim((string) $description);

        return '' === $description ? null : $description;
    }
}
