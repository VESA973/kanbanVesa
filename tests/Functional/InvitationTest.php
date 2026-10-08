<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Invitation;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectRole;
use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;
use App\Repository\InvitationRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

use function Zenstruck\Foundry\force;
use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

final class InvitationTest extends FunctionalTestCase
{
    public function testOwnerInvitesANewcomerWhoRegistersAndJoinsTheProject(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $client->loginUser($owner);

        $link = $this->invite($client, $project, 'alex@example.com', 'viewer');
        $client->request('GET', '/logout');
        $client->getCookieJar()->clear();

        $client->request('GET', $link);
        self::assertSelectorTextContains('main', 'vous invite à rejoindre le projet');
        $client->clickLink('Créer un compte');
        $client->submitForm('Créer mon compte', [
            'registration_form[firstName]' => 'Alex',
            'registration_form[lastName]' => 'Martin',
            'registration_form[email]' => 'alex@example.com',
            'registration_form[plainPassword][first]' => 'motdepasse',
            'registration_form[plainPassword][second]' => 'motdepasse',
        ]);
        self::assertResponseRedirects($link, message: 'After registering, the newcomer comes back to the invitation.');

        $client->followRedirect();
        $client->submitForm('Rejoindre le projet');

        self::assertResponseRedirects('/projects/'.$project->getId());
        $alex = UserFactory::repository()->findOneBy(['email' => 'alex@example.com']) ?? throw new \LogicException();
        self::assertSame(ProjectRole::VIEWER, refresh($project)->getRoleOf($alex));
        self::assertTrue($alex->isVerified());
    }

    public function testInvitationCannotBeAcceptedWithAnotherAccount(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $client->loginUser($owner);
        $link = $this->invite($client, $project, 'alex@example.com', 'editor');

        $mallory = UserFactory::createOne(['email' => 'mallory@example.com']);
        $client->loginUser($mallory);
        $client->request('GET', $link);

        self::assertSelectorTextContains('[role=alert]', 'une autre adresse e-mail');
        self::assertSelectorNotExists('form[action$="/accept"]');
        self::assertNull(refresh($project)->getRoleOf($mallory));
    }

    public function testInvitationLinkWorksOnlyOnce(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $alex = UserFactory::createOne(['email' => 'alex@example.com']);
        $client->loginUser($owner);
        $link = $this->invite($client, $project, 'alex@example.com', 'editor');

        $client->loginUser($alex);
        $client->request('GET', $link);
        $client->submitForm('Rejoindre le projet');
        $client->request('GET', $link);

        self::assertResponseStatusCodeSame(410);
        self::assertSelectorTextContains('[role=alert]', 'déjà été utilisée');
    }

    public function testInvitingAnExistingMemberShowsAnError(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $client->loginUser($owner);
        $client->request('GET', '/projects/'.$project->getId().'/members');

        $client->submitForm("Envoyer l'invitation", ['invitation_form[email]' => $owner->getEmail()]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="invitation_form"]', 'déjà membre du projet');
        self::assertQueuedEmailCount(0);
    }

    public function testOwnerRevokesAPendingInvitation(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $client->loginUser($owner);
        $this->invite($client, $project, 'alex@example.com', 'editor');

        $client->request('GET', '/projects/'.$project->getId().'/members');
        $client->submitForm('Annuler');

        self::assertResponseRedirects('/projects/'.$project->getId().'/members');
        self::assertSame(0, self::getContainer()->get(InvitationRepository::class)->count());
    }

    public function testOwnerSeesTheStatusOfInvitationsAndResendsAnExpiredOne(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $client->loginUser($owner);
        $this->invite($client, $project, 'alex@example.com', 'editor');
        $invitation = self::getContainer()->get(InvitationRepository::class)->findOneBy(['email' => 'alex@example.com']) ?? throw new \LogicException();
        new \ReflectionProperty(Invitation::class, 'expiresAt')->setValue($invitation, new \DateTimeImmutable('-1 day'));
        self::getContainer()->get(InvitationRepository::class)->save($invitation);

        $crawler = $client->request('GET', '/projects/'.$project->getId().'/members');
        self::assertSelectorTextContains('#invitations-heading + ul', 'Expirée');
        self::assertCount(0, $crawler->filter('form[action$="/link"]'), 'An expired link is not shared any more.');

        $client->submit($crawler->filter('form[action="/invitations/'.$invitation->getId().'/resend"]')->form());
        self::assertResponseRedirects('/projects/'.$project->getId().'/members');
        self::assertQueuedEmailCount(1);
        $client->followRedirect();
        self::assertSelectorTextContains('#invitations-heading + ul', 'En attente');
        self::assertSelectorTextContains('main', 'renvoyée');
    }

    public function testEditorSeesMembersButCannotManageThem(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $editor = $this->member($project, ProjectRole::EDITOR);
        $client->loginUser($editor);

        $client->request('GET', '/projects/'.$project->getId().'/members');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[name="invitation_form"]');
        self::assertSelectorNotExists('form[action*="/members/"]');

        $client->request('POST', '/projects/'.$project->getId().'/invitations', ['invitation_form' => ['email' => 'x@example.com', 'role' => 'editor']]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testNonMemberGetsA404OnTheMembersPage(): void
    {
        $client = self::createClient();
        [, $project] = $this->project();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/projects/'.$project->getId().'/members');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOwnerChangesARoleAndRemovesAMember(): void
    {
        $client = self::createClient();
        [$owner, $project] = $this->project();
        $alex = $this->member($project, ProjectRole::VIEWER);
        $column = $project->getColumns()->first() ?: throw new \LogicException();
        $task = TaskFactory::new()->inColumn($column)->create(['assignee' => force($alex)]);
        $client->loginUser($owner);

        $crawler = $client->request('GET', '/projects/'.$project->getId().'/members');
        $client->submit($crawler->filter('form[action$="/role"]')->form(['role' => 'editor']));
        self::assertSame(ProjectRole::EDITOR, refresh($project)->getRoleOf($alex));

        $crawler = $client->request('GET', '/projects/'.$project->getId().'/members');
        $client->submit($crawler->filter('form[action$="/remove"]')->form());

        self::assertNull(refresh($project)->getRoleOf($alex));
        self::assertNull(refresh($task)->getAssignee(), 'A removed member keeps no assigned task.');
    }

    /**
     * @return array{User, Project}
     */
    private function project(): array
    {
        $owner = UserFactory::createOne();

        return [$owner, ProjectFactory::new()->withColumns('À faire')->create(['owner' => $owner])];
    }

    private function member(Project $project, ProjectRole $role): User
    {
        $user = UserFactory::createOne();
        $project->addMember($user, $role);
        save($project);

        return $user;
    }

    /**
     * @return string the invitation link found in the e-mail
     */
    private function invite(KernelBrowser $client, Project $project, string $email, string $role): string
    {
        $client->request('GET', '/projects/'.$project->getId().'/members');
        $client->submitForm("Envoyer l'invitation", ['invitation_form[email]' => $email, 'invitation_form[role]' => $role]);
        self::assertResponseRedirects('/projects/'.$project->getId().'/members');

        $link = self::extractLink(self::getMailerMessage());
        self::assertStringContainsString('/invitations/', $link);
        self::assertSame(1, self::getContainer()->get(InvitationRepository::class)->count(['email' => $email]));

        return (string) parse_url($link, \PHP_URL_PATH);
    }
}
