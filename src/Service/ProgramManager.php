<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Program;
use App\Entity\User;
use App\Exception\ProgramException;
use App\Form\Data\ProgramData;
use App\Repository\ProgramRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ProgramManager
{
    public function __construct(
        private ProgramRepository $programRepository,
        private TranslatorInterface $translator,
        private ProgramImageStorage $imageStorage,
    ) {
    }

    /**
     * The creator becomes the owner (see Program::__construct).
     */
    public function create(ProgramData $data, User $owner): Program
    {
        $program = new Program($data->name, $owner, $data->color, $data->description);
        $this->applyImage($program, $data);
        $this->programRepository->save($program);

        return $program;
    }

    public function update(Program $program, ProgramData $data): void
    {
        $program->update($data->name, $data->description, $data->color);
        $this->applyImage($program, $data);
        $this->programRepository->save($program);
    }

    /**
     * Where a user's project goes when they pick no program: their oldest one, or a new "Général".
     */
    public function defaultFor(User $user): Program
    {
        return $this->programRepository->findDefaultFor($user)
            ?? new Program($this->translator->trans('program.default_name'), $user);
    }

    /**
     * Projects are never deleted along with their program: it must be emptied first.
     *
     * @throws ProgramException when the program still contains projects
     */
    public function delete(Program $program): void
    {
        if (!$program->getProjects()->isEmpty()) {
            throw ProgramException::notEmpty();
        }

        $this->imageStorage->remove($program);
        $this->programRepository->remove($program);
    }

    private function applyImage(Program $program, ProgramData $data): void
    {
        if (null !== $data->image) {
            $this->imageStorage->store($program, $data->image);
        } elseif ($data->removeImage) {
            $this->imageStorage->remove($program);
        }
    }
}
