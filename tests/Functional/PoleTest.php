<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ProgramFactory;
use App\Factory\UserFactory;
use App\Repository\PoleRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class PoleTest extends FunctionalTestCase
{
    public function testAUserSortsTheirProjectsByPole(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $town = ProgramFactory::createOne(['name' => 'Mairie 2027', 'owner' => $user]);
        ProgramFactory::createOne(['name' => 'Repas solidaires', 'owner' => $user]);
        $client->loginUser($user);

        $this->createPole($client, 'Évangélisation');
        $this->createPole($client, 'Social');
        $social = self::getContainer()->get(PoleRepository::class)->findOneBy(['name' => 'Social']) ?? throw new \LogicException();

        $crawler = $client->request('GET', '/programs/'.$town->getId());
        $client->submit($crawler->filter('form[action$="/pole"]')->form(['poleId' => (string) $social->getId()]));
        self::assertResponseRedirects('/programs/'.$town->getId());

        $crawler = $client->request('GET', '/projects');
        self::assertSame(['Évangélisation', 'Social', 'Sans pôle'], $crawler->filter('main section > header h2')->each(static fn ($title): string => trim($title->text())));
        self::assertStringContainsString('Mairie 2027', $crawler->filter('section[aria-labelledby="pole-'.$social->getId().'-title"]')->text());
        self::assertStringContainsString('Repas solidaires', $crawler->filter('section[aria-labelledby="pole-none-title"]')->text());

        $crawler = $client->request('GET', '/projects?pole='.$social->getId());
        self::assertSame(['Mairie 2027'], $crawler->filter('main article h2')->each(static fn ($title): string => trim($title->text())));

        $crawler = $client->request('GET', '/programs/'.$town->getId());
        $client->submit($crawler->filter('form[action$="/pole"]')->form(['poleId' => '']));
        self::assertResponseRedirects('/programs/'.$town->getId());
        $crawler = $client->request('GET', '/projects?pole=none');
        self::assertSame(['Mairie 2027', 'Repas solidaires'], $crawler->filter('main article h2')->each(static fn ($title): string => trim($title->text())));
    }

    public function testDeletingAPoleKeepsItsProjects(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $town = ProgramFactory::createOne(['name' => 'Mairie 2027', 'owner' => $user]);
        $client->loginUser($user);
        $this->createPole($client, 'Social');
        $pole = self::getContainer()->get(PoleRepository::class)->findOneBy(['name' => 'Social']) ?? throw new \LogicException();
        $crawler = $client->request('GET', '/programs/'.$town->getId());
        $client->submit($crawler->filter('form[action$="/pole"]')->form(['poleId' => (string) $pole->getId()]));

        $crawler = $client->request('GET', '/poles');
        $client->submit($crawler->filter('form[action$="/delete"]')->form());
        $client->followRedirect();

        self::assertSelectorTextContains('main', 'Sans pôle');
        $client->request('GET', '/projects');
        self::assertSelectorTextContains('main article', 'Mairie 2027');
    }

    public function testPolesArePersonal(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne();
        $client->loginUser($owner);
        $this->createPole($client, 'Social');
        $pole = self::getContainer()->get(PoleRepository::class)->findOneBy(['name' => 'Social']) ?? throw new \LogicException();

        $intruder = UserFactory::createOne();
        $program = ProgramFactory::createOne(['owner' => $intruder]);
        $client->loginUser($intruder);
        $crawler = $client->request('GET', '/poles');
        self::assertCount(0, $crawler->filter('form[action$="/rename"]'), 'Nobody else sees my poles.');

        // Filing one's program in someone else's pole is refused as if the pole did not exist.
        $crawler = $client->request('GET', '/programs/'.$program->getId());
        $token = $crawler->filter('form[action$="/pole"] input[name="_token"]')->attr('value');
        $client->request('POST', '/programs/'.$program->getId().'/pole', ['_token' => $token, 'poleId' => (string) $pole->getId()]);
        self::assertResponseStatusCodeSame(404);
        self::assertCount(0, $pole->getPrograms());
    }

    private function createPole(KernelBrowser $client, string $name): void
    {
        $crawler = $client->request('GET', '/poles');
        $client->submit($crawler->filter('form[action="/poles"]')->form(['name' => $name]));
        self::assertResponseRedirects('/poles');
    }
}
