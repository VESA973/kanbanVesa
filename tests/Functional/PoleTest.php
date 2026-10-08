<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\ProgramFactory;
use App\Factory\UserFactory;
use App\Repository\PoleRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class PoleTest extends FunctionalTestCase
{
    public function testAUserCreatesPolesClicksOneAndFillsIt(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $town = ProgramFactory::createOne(['name' => 'Mairie 2027', 'owner' => $user]);
        ProgramFactory::createOne(['name' => 'Repas solidaires', 'owner' => $user]);
        $client->loginUser($user);

        // Without poles: the project cards and an invitation to create one, which opens it.
        $crawler = $client->request('GET', '/projects');
        self::assertSelectorTextContains('main', 'créez votre premier pôle');
        $client->submit($crawler->filter('form[action="/poles"]')->form(['name' => 'Social']));
        $social = self::getContainer()->get(PoleRepository::class)->findOneBy(['name' => 'Social']) ?? throw new \LogicException();
        self::assertResponseRedirects('/poles/'.$social->getId());

        $crawler = $client->followRedirect();
        $client->submit($crawler->filter('form[action$="/programs"]')->form()->setValues(['programIds' => [(string) $town->getId()]]));
        $crawler = $client->followRedirect();
        self::assertSame(['Mairie 2027'], $crawler->filter('main ul article h2')->each(static fn ($title): string => trim($title->text())));

        // "Mes projets" now shows pole cards; a click on one shows its projects.
        $crawler = $client->request('GET', '/projects');
        self::assertSame(['Social', 'Sans pôle'], $crawler->filter('main article h2')->each(static fn ($title): string => trim($title->text())));
        self::assertSelectorTextContains('main article', '1 projet');
        $crawler = $client->click($crawler->selectLink('Sans pôle')->link());
        self::assertSelectorTextContains('main ul', 'Repas solidaires');

        // Filing straight from a project card, back on the same page.
        $client->submit($crawler->filter('form[action$="/pole"]')->form(['poleId' => (string) $social->getId()]));
        self::assertResponseRedirects('/poles/none');
        $crawler = $client->request('GET', '/poles/'.$social->getId());
        self::assertSame(['Mairie 2027', 'Repas solidaires'], $crawler->filter('main ul article h2')->each(static fn ($title): string => trim($title->text())));
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

        $client->request('GET', '/projects');
        self::assertSelectorTextContains('main article', 'Mairie 2027', 'No pole left: the project cards are back.');
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
