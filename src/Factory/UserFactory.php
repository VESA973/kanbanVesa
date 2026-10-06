<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public const string DEFAULT_PASSWORD = 'password123';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    public static function class(): string
    {
        return User::class;
    }

    public function verified(): self
    {
        return $this->afterInstantiate(static fn (User $user) => $user->markAsVerified());
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'plainPassword' => self::DEFAULT_PASSWORD,
        ];
    }

    protected function initialize(): static
    {
        return $this
            ->instantiateWith(Instantiator::withConstructor()->allowExtra('plainPassword'))
            ->afterInstantiate(function (User $user, array $attributes): void {
                $plainPassword = $attributes['plainPassword'] ?? null;
                if (\is_string($plainPassword)) {
                    $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
                }
            });
    }
}
