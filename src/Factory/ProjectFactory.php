<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Program;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectColor;
use App\Enum\ProjectRole;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Project>
 */
final class ProjectFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Project::class;
    }

    /**
     * Like ProjectCreator without a chosen program: each project goes to a new "Général" program of its owner.
     */
    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (Project $project): void {
            $project->placeIn(new Program('Général', $project->getOwner()));
        });
    }

    /**
     * Places the project in $program; inherited access of its members is not created (use ProgramAccess).
     */
    public function inProgram(Program $program): self
    {
        return $this->afterInstantiate(static function (Project $project) use ($program): void {
            $project->placeIn($program);
        });
    }

    public function withColumns(string ...$names): self
    {
        return $this->afterInstantiate(static function (Project $project) use ($names): void {
            foreach ($names as $name) {
                $project->addColumn($name);
            }
        });
    }

    public function withMember(User $user, ProjectRole $role): self
    {
        return $this->afterInstantiate(static function (Project $project) use ($user, $role): void {
            $project->addMember($user, $role);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'name' => rtrim(self::faker()->sentence(3), '.'),
            'description' => self::faker()->optional()->sentence(),
            'color' => self::faker()->randomElement(ProjectColor::cases()),
            'owner' => UserFactory::new(),
        ];
    }
}
