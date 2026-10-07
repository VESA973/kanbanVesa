<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The only way to create the first administrator: it needs access to the server.
 */
#[AsCommand(name: 'app:user:promote', description: 'Gives (or removes with --demote) the administrator role to a user')]
final readonly class PromoteUserCommand
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'E-mail address of the account')] string $email,
        #[Option(description: 'Remove the administrator role instead')] bool $demote = false,
    ): int {
        $user = $this->userRepository->findOneByEmail($email);
        if (null === $user) {
            $io->error(\sprintf('No account found for "%s".', $email));

            return Command::FAILURE;
        }

        $demote ? $user->demoteFromAdmin() : $user->promoteToAdmin();
        $this->userRepository->save($user);
        $io->success(\sprintf('%s is %s an administrator.', $user->getEmail(), $demote ? 'no longer' : 'now'));

        return Command::SUCCESS;
    }
}
