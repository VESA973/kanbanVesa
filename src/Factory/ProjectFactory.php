<?php

declare(strict_types=1);

namespace App\Factory;

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
     * Like ProjectCreator, every project starts with one category (tasks need one).
     */
    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (Project $project): void {
            $project->addCategory('Général');
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

    public function withCategories(string ...$names): self
    {
        return $this->afterInstantiate(static function (Project $project) use ($names): void {
            foreach ($names as $name) {
                $project->addCategory($name);
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
