<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Factory\ProjectFactory;
use App\Factory\TaskFactory;
use App\Factory\UserFactory;
use App\Repository\SmtpSettingsRepository;
use App\Service\SecretBox;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Tester\CommandTester;

use function Zenstruck\Foundry\Persistence\refresh;

final class AdminTest extends FunctionalTestCase
{
    public function testAdministrationIsReservedToAdmins(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        foreach (['/admin/users', '/admin/smtp'] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(403);
        }
        $client->request('GET', '/projects');
        self::assertSelectorNotExists('nav a[href="/admin"]');
    }

    public function testPromoteCommandCreatesTheFirstAdmin(): void
    {
        self::bootKernel();
        $user = UserFactory::createOne(['email' => 'chef@example.com']);
        $command = new CommandTester(new Application(self::$kernel ?? throw new \LogicException())->find('app:user:promote'));

        $command->execute(['email' => 'Chef@Example.com']);

        self::assertStringContainsString('now an administrator', $command->getDisplay());
        self::assertTrue(refresh($user)->isAdmin());
    }

    public function testDeactivatedUserCannotLogInAndIsLoggedOut(): void
    {
        $client = self::createClient();
        $admin = $this->admin();
        $alex = UserFactory::new()->verified()->create(['email' => 'alex@example.com']);

        $client->loginUser($alex);
        $client->request('GET', '/projects');
        self::assertResponseIsSuccessful();

        $client->loginUser($admin);
        $this->clickAction($client, $alex, 'Désactiver');
        self::assertFalse(refresh($alex)->isActive());

        $client->loginUser($alex);
        $client->request('GET', '/projects');
        self::assertResponseRedirects('/login', message: 'An open session is closed.');

        $client->request('GET', '/login');
        $client->submitForm('Se connecter', ['email' => 'alex@example.com', 'password' => UserFactory::DEFAULT_PASSWORD]);
        $client->followRedirect();
        self::assertSelectorTextContains('[role=alert]', 'désactivé');
    }

    public function testAdminVerifiesPromotesAndDeletesAccounts(): void
    {
        $client = self::createClient();
        $admin = $this->admin();
        $alex = UserFactory::createOne(['firstName' => 'Alex']);
        $project = ProjectFactory::new()->withColumns('À faire')->create(['owner' => $admin]);
        $task = TaskFactory::new()->inColumn($project->getColumns()->first() ?: throw new \LogicException())->create(['createdBy' => $alex]);
        $client->loginUser($admin);

        $this->clickAction($client, $alex, "Vérifier l'e-mail");
        $this->clickAction($client, $alex, 'Nommer admin');
        self::assertTrue(refresh($alex)->isVerified());
        self::assertTrue(refresh($alex)->isAdmin());

        $alexId = $alex->getId();
        $this->clickAction($client, $alex, 'Supprimer');
        self::assertNull(UserFactory::repository()->find($alexId));
        self::assertNull(refresh($task)->getCreatedBy(), 'The task stays, without author.');
    }

    public function testProjectOwnersAndOneselfCannotBeDeleted(): void
    {
        $client = self::createClient();
        $admin = $this->admin();
        $owner = UserFactory::createOne();
        ProjectFactory::createOne(['owner' => $owner]);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/users');
        self::assertCount(0, $crawler->filter('form[action="/admin/users/'.$owner->getId().'/delete"]'));
        self::assertCount(0, $crawler->filter('form[action^="/admin/users/'.$admin->getId().'/"]'), 'No action on oneself.');

        $client->request('POST', '/admin/users/'.$admin->getId().'/toggle-active');
        self::assertTrue(refresh($admin)->isActive());
    }

    public function testSmtpSettingsAreSavedWithAnEncryptedPassword(): void
    {
        $client = self::createClient();
        $client->loginUser($this->admin());
        $client->request('GET', '/admin/smtp');

        $client->submitForm('Enregistrer', [
            'smtp_settings_form[host]' => 'smtp.example.com',
            'smtp_settings_form[port]' => '587',
            'smtp_settings_form[encryption]' => 'starttls',
            'smtp_settings_form[username]' => 'mailer@example.com',
            'smtp_settings_form[password]' => 'tres-secret',
            'smtp_settings_form[fromAddress]' => 'noreply@example.com',
            'smtp_settings_form[fromName]' => 'TaskBoard',
        ]);
        self::assertResponseRedirects('/admin/smtp');

        $settings = self::getContainer()->get(SmtpSettingsRepository::class)->findCurrent() ?? throw new \LogicException();
        self::assertNotNull($settings->getEncryptedPassword());
        self::assertStringNotContainsString('tres-secret', $settings->getEncryptedPassword());
        self::assertSame('tres-secret', self::getContainer()->get(SecretBox::class)->decrypt($settings->getEncryptedPassword()));

        $client->request('GET', '/admin/smtp');
        $client->submitForm('Enregistrer', ['smtp_settings_form[password]' => '']);
        $settings = self::getContainer()->get(SmtpSettingsRepository::class)->findCurrent() ?? throw new \LogicException();
        self::assertSame('tres-secret', self::getContainer()->get(SecretBox::class)->decrypt((string) $settings->getEncryptedPassword()), 'An empty field keeps the password.');
    }

    public function testSmtpTestShowsTheServerError(): void
    {
        $client = self::createClient();
        $client->loginUser($this->admin());
        $client->request('GET', '/admin/smtp');
        $client->submitForm('Enregistrer', [
            'smtp_settings_form[host]' => '127.0.0.1',
            'smtp_settings_form[port]' => '1',
            'smtp_settings_form[encryption]' => 'none',
            'smtp_settings_form[fromAddress]' => 'noreply@example.com',
        ]);

        $client->request('GET', '/admin/smtp');
        $client->submitForm('Envoyer un e-mail de test');
        $client->followRedirect();

        self::assertSelectorTextContains('[role=alert]', "Échec de l'envoi");
    }

    private function admin(): User
    {
        $admin = UserFactory::new()->verified()->create();
        $admin->promoteToAdmin();
        UserFactory::repository()->assert()->exists(['id' => $admin->getId()]);
        self::getContainer()->get('doctrine')->getManager()->flush();

        return $admin;
    }

    private function clickAction(KernelBrowser $client, User $user, string $label): void
    {
        $crawler = $client->request('GET', '/admin/users');
        $form = $crawler->filter('form[action^="/admin/users/'.$user->getId().'/"]')
            ->reduce(static fn ($form): bool => str_starts_with(trim($form->filter('button')->text()), $label))
            ->form();
        $client->submit($form);
        self::assertResponseRedirects('/admin/users');
    }
}
