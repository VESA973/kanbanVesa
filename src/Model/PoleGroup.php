<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Pole;

/**
 * A pole of the user (null: "Sans pôle") with the programs filed in it, on "Mes projets".
 */
final readonly class PoleGroup
{
    /**
     * @param list<ProgramSection> $sections
     */
    public function __construct(
        public ?Pole $pole,
        public array $sections,
    ) {
    }

    /**
     * All the chantiers of its programs, weighted by their number of tasks.
     */
    public function overall(): Progress
    {
        return array_reduce($this->sections, static fn (Progress $sum, ProgramSection $section): Progress => $sum->add($section->overall()), new Progress());
    }
}
