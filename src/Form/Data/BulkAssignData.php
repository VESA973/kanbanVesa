<?php

declare(strict_types=1);

namespace App\Form\Data;

use App\Entity\BoardColumn;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

final class BulkAssignData
{
    /** @var list<User> restricted to the project members by BulkAssignFormType */
    #[Assert\Count(min: 1, minMessage: 'bulk_assign.assignees.required')]
    public array $assignees = [];

    /** Null for every column of the project. */
    public ?BoardColumn $column = null;
}
