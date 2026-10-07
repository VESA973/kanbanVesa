<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DueDateReminder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Meant to run once a day from cron, e.g. "0 7 * * * php bin/console app:tasks:remind-due".
 */
#[AsCommand(name: 'app:tasks:remind-due', description: 'E-mails the assignees of open tasks due tomorrow')]
final readonly class RemindDueTasksCommand
{
    public function __construct(
        private DueDateReminder $dueDateReminder,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $count = $this->dueDateReminder->remindTasksDueTomorrow();
        $io->success(\sprintf('%d reminder(s) queued.', $count));

        return Command::SUCCESS;
    }
}
