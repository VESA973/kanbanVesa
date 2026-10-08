<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Program;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Illustrations of programs, kept outside public/ so that only people with access
 * to the program can see them (served by ProgramController::image()).
 */
final readonly class ProgramImageStorage
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/uploads/%kernel.environment%/programs')]
        private string $directory,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * Replaces the current image. The name is random: it reveals nothing and changes
     * with each upload, so browsers never show a stale image.
     */
    public function store(Program $program, UploadedFile $file): void
    {
        $filename = bin2hex(random_bytes(16)).'.'.($file->guessExtension() ?? 'bin');
        $file->move($this->directory, $filename);
        $this->remove($program);
        $program->setImageFilename($filename);
    }

    public function remove(Program $program): void
    {
        $path = $this->pathOf($program);
        if (null !== $path) {
            $this->filesystem->remove($path);
        }
        $program->setImageFilename(null);
    }

    public function pathOf(Program $program): ?string
    {
        $filename = $program->getImageFilename();

        return null === $filename ? null : $this->directory.'/'.$filename;
    }
}
