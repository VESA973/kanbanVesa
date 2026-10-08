<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Program;
use App\Entity\User;
use App\Enum\ProjectColor;
use App\Enum\ProjectRole;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Program>
 */
final class ProgramFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Program::class;
    }

    /**
     * A direct program member, without propagating to projects (use ProgramMembership for that).
     */
    public function withMember(User $user, ProjectRole $role): self
    {
        return $this->afterInstantiate(static function (Program $program) use ($user, $role): void {
            $program->addMember($user, $role);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'name' => rtrim(self::faker()->sentence(2), '.'),
            'color' => self::faker()->randomElement(ProjectColor::cases()),
            'owner' => UserFactory::new(),
        ];
    }
}
