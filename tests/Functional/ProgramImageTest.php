<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ProgramFactory;
use App\Factory\ProjectFactory;
use App\Factory\UserFactory;
use App\Repository\ProgramRepository;
use App\Service\ProgramImageStorage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Zenstruck\Foundry\Persistence\refresh;

final class ProgramImageTest extends FunctionalTestCase
{
    public function testTheOwnerIllustratesAProjectAndOnlyPeopleWithAccessSeeTheImage(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne();
        $client->loginUser($owner);

        $client->request('GET', '/programs/new');
        $client->submitForm('Créer le projet', [
            'project_form[name]' => 'Mairie 2027',
            'project_form[image]' => $this->png(),
        ]);
        $program = self::getContainer()->get(ProgramRepository::class)->findOneBy(['name' => 'Mairie 2027']) ?? throw new \LogicException();
        $filename = $program->getImageFilename() ?? throw new \LogicException('The image was stored.');
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}\.png$/', $filename, 'A random name, not the uploaded one.');

        $crawler = $client->request('GET', '/projects');
        $src = $crawler->filter('main article img')->attr('src');
        self::assertSame('/programs/'.$program->getId().'/image?v='.$filename, $src);
        $client->request('GET', $src);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');

        $client->loginUser(UserFactory::createOne());
        $client->request('GET', $src);
        self::assertResponseStatusCodeSame(404);

        $this->cleanUp($program->getId());
    }

    public function testReplacingThenRemovingTheImageDeletesTheFiles(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne();
        $program = ProgramFactory::createOne(['owner' => $owner]);
        $client->loginUser($owner);

        $client->request('GET', '/programs/'.$program->getId().'/edit');
        $client->submitForm('Enregistrer', ['project_form[image]' => $this->png()]);
        $first = $this->path($program->getId());
        self::assertFileExists($first);

        $client->request('GET', '/programs/'.$program->getId().'/edit');
        $client->submitForm('Enregistrer', ['project_form[image]' => $this->png()]);
        $second = $this->path($program->getId());
        self::assertFileExists($second);
        self::assertFileDoesNotExist($first, 'The previous image is deleted.');

        $client->request('GET', '/programs/'.$program->getId().'/edit');
        $client->submitForm('Enregistrer', ['project_form[removeImage]' => true]);
        self::assertFileDoesNotExist($second);
        self::assertNull(refresh($program)->getImageFilename());
    }

    public function testOnlyImagesAreAccepted(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne();
        $program = ProgramFactory::createOne(['owner' => $owner]);
        ProjectFactory::new()->inProgram($program)->create(['owner' => $owner]);
        $client->loginUser($owner);
        $text = tempnam(sys_get_temp_dir(), 'txt') ?: throw new \LogicException();
        file_put_contents($text, 'pas une image');

        $client->request('GET', '/programs/'.$program->getId().'/edit');
        $client->submitForm('Enregistrer', ['project_form[image]' => new UploadedFile($text, 'photo.png', null, null, true)]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="project_form"]', 'Choisissez une image JPG, PNG ou WebP.');
        self::assertNull(refresh($program)->getImageFilename());
    }

    private function png(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img') ?: throw new \LogicException();
        $image = imagecreatetruecolor(32, 18) ?: throw new \LogicException();
        imagepng($image, $path);

        return new UploadedFile($path, 'mairie.png', 'image/png', null, true);
    }

    private function path(?int $programId): string
    {
        $program = self::getContainer()->get(ProgramRepository::class)->find($programId) ?? throw new \LogicException();

        return self::getContainer()->get(ProgramImageStorage::class)->pathOf($program) ?? throw new \LogicException('No image stored.');
    }

    private function cleanUp(?int $programId): void
    {
        $program = self::getContainer()->get(ProgramRepository::class)->find($programId) ?? throw new \LogicException();
        self::getContainer()->get(ProgramImageStorage::class)->remove($program);
    }
}
