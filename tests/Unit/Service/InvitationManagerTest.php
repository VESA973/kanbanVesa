<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Invitation;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\InvitationStatus;
use App\Enum\ProjectRole;
use App\Exception\InvitationException;
use App\Repository\InvitationRepository;
use App\Service\InvitationManager;
use App\Tests\Unit\BuildsTasks;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class InvitationManagerTest extends TestCase
{
    use BuildsTasks;

    private User $owner;
    private Project $project;

    protected function setUp(): void
    {
        $this->owner = new User('owner@example.com', 'Olivia', 'Owner');
        $this->project = new Project('Projet', $this->owner);
    }

    public function testInviteStoresOnlyTheHashAndMailsThePlainToken(): void
    {
        $sentToken = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send')->willReturnCallback(static function (TemplatedEmail $email) use (&$sentToken): void {
            $sentToken = $email->getContext()['token'];
        });

        $invitation = $this->manager(mailer: $mailer)->invite($this->project, ' Alex@Example.com ', ProjectRole::EDITOR, $this->owner);

        self::assertSame('alex@example.com', $invitation->getEmail());
        self::assertIsString($sentToken);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $sentToken);
        self::assertSame(Invitation::hashToken($sentToken), new \ReflectionProperty(Invitation::class, 'tokenHash')->getValue($invitation));
    }

    public function testInvitingTheSameAddressAgainRenewsThePendingInvitation(): void
    {
        $existing = new Invitation($this->project, 'alex@example.com', ProjectRole::VIEWER, $this->owner, 'old-token');
        $repository = $this->createStub(InvitationRepository::class);
        $repository->method('findNotAcceptedFor')->willReturn($existing);

        $invitation = $this->manager($repository)->invite($this->project, 'alex@example.com', ProjectRole::EDITOR, $this->owner);

        self::assertSame($existing, $invitation);
        self::assertSame(ProjectRole::EDITOR, $invitation->getRole());
        self::assertNotSame(Invitation::hashToken('old-token'), new \ReflectionProperty(Invitation::class, 'tokenHash')->getValue($invitation));
    }

    public function testCannotInviteAnExistingMember(): void
    {
        $this->expectExceptionObject(InvitationException::alreadyMember());

        $this->manager()->invite($this->project, 'OWNER@example.com', ProjectRole::EDITOR, $this->owner);
    }

    public function testAcceptAddsTheMemberWithTheInvitedRoleAndVerifiesTheAccount(): void
    {
        $invitation = new Invitation($this->project, 'alex@example.com', ProjectRole::VIEWER, $this->owner, 'secret');
        $alex = new User('alex@example.com', 'Alex', 'Martin');

        $project = $this->manager($this->repositoryFinding($invitation))->accept('secret', $alex);

        self::assertSame($this->project, $project);
        self::assertSame(ProjectRole::VIEWER, $project->getRoleOf($alex));
        self::assertTrue($alex->isVerified());
        self::assertTrue($invitation->isAccepted());
    }

    public function testAcceptingAnInvitationForATaskAssignsIt(): void
    {
        $task = self::newTask($this->project->addColumn('À faire'), 'Distribuer les flyers', $this->owner);
        $invitation = new Invitation($this->project, 'alex@example.com', ProjectRole::VIEWER, $this->owner, 'secret');
        $invitation->forTask($task);
        $alex = new User('alex@example.com', 'Alex', 'Martin');

        $this->manager($this->repositoryFinding($invitation))->accept('secret', $alex);

        self::assertSame($alex, $task->getAssignee());
        self::assertTrue($alex->isVerified(), 'A link received by e-mail proves the address.');
    }

    public function testAHandSharedLinkDoesNotVerifyTheAccount(): void
    {
        $invitation = new Invitation($this->project, 'alex@example.com', ProjectRole::VIEWER, $this->owner, 'emailed-token');
        $manager = $this->manager($this->repositoryFinding($invitation));

        $sharedToken = $manager->createShareableLink($invitation);
        self::assertTrue($invitation->isLinkShared());
        self::assertSame(Invitation::hashToken($sharedToken), new \ReflectionProperty(Invitation::class, 'tokenHash')->getValue($invitation));

        $alex = new User('alex@example.com', 'Alex', 'Martin');
        $manager->accept($sharedToken, $alex);

        self::assertSame(ProjectRole::VIEWER, $this->project->getRoleOf($alex));
        self::assertFalse($alex->isVerified());
    }

    public function testATaskOfAnotherProjectIsRefused(): void
    {
        $otherTask = self::newTask(new Project('Autre', $this->owner)->addColumn('À faire'), 'Tâche', $this->owner);
        $invitation = new Invitation($this->project, 'alex@example.com', ProjectRole::VIEWER, $this->owner, 'secret');

        $this->expectException(\InvalidArgumentException::class);

        $invitation->forTask($otherTask);
    }

    public function testAcceptRefusesAnotherAccount(): void
    {
        $invitation = new Invitation($this->project, 'alex@example.com', ProjectRole::VIEWER, $this->owner, 'secret');

        $this->expectExceptionObject(InvitationException::emailMismatch());

        $this->manager($this->repositoryFinding($invitation))->accept('secret', new User('mallory@example.com', 'Mallory', 'Evil'));
    }

    public function testAnInvitationCanBeUsedOnlyOnce(): void
    {
        $invitation = new Invitation($this->project, 'alex@example.com', ProjectRole::VIEWER, $this->owner, 'secret');
        $invitation->markAsAccepted();

        $this->expectExceptionObject(InvitationException::alreadyUsed());

        $this->manager($this->repositoryFinding($invitation))->findValid('secret');
    }

    public function testExpiredInvitationIsRefused(): void
    {
        $invitation = new Invitation($this->project, 'alex@example.com', ProjectRole::VIEWER, $this->owner, 'secret');
        new \ReflectionProperty(Invitation::class, 'expiresAt')->setValue($invitation, new \DateTimeImmutable('-1 minute'));

        $this->expectExceptionObject(InvitationException::expired());

        $this->manager($this->repositoryFinding($invitation))->findValid('secret');
    }

    public function testUnknownTokenIsRefused(): void
    {
        $this->expectExceptionObject(InvitationException::notFound());

        $this->manager($this->repositoryFinding(null))->findValid('unknown');
    }

    public function testResendRenewsTheTokenAndTheDelayThenMailsAgain(): void
    {
        $invitation = new Invitation($this->project, 'alex@example.com', ProjectRole::VIEWER, $this->owner, 'old-token');
        new \ReflectionProperty(Invitation::class, 'expiresAt')->setValue($invitation, new \DateTimeImmutable('-1 day'));
        self::assertSame(InvitationStatus::EXPIRED, $invitation->getStatus());
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $this->manager(mailer: $mailer)->resend($invitation);

        self::assertSame(InvitationStatus::PENDING, $invitation->getStatus());
        self::assertGreaterThan(new \DateTimeImmutable('+6 days'), $invitation->getExpiresAt());
        self::assertNotSame(Invitation::hashToken('old-token'), new \ReflectionProperty(Invitation::class, 'tokenHash')->getValue($invitation));
    }

    public function testAnAcceptedInvitationCanBeNeitherResentNorRevoked(): void
    {
        $invitation = new Invitation($this->project, 'alex@example.com', ProjectRole::VIEWER, $this->owner, 'secret');
        $invitation->markAsAccepted();
        self::assertSame(InvitationStatus::ACCEPTED, $invitation->getStatus());
        $repository = $this->createMock(InvitationRepository::class);
        $repository->expects($this->never())->method('remove');
        $repository->expects($this->never())->method('save');

        foreach (['resend', 'revoke'] as $action) {
            try {
                $this->manager($repository)->{$action}($invitation);
                self::fail($action.' should have been refused.');
            } catch (InvitationException $exception) {
                self::assertSame('invitation.error.already_used', $exception->getMessage());
            }
        }
    }

    public function testNobodyCanBeInvitedAsOwner(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Invitation($this->project, 'alex@example.com', ProjectRole::OWNER, $this->owner, 'secret');
    }

    private function manager(?InvitationRepository $repository = null, ?MailerInterface $mailer = null): InvitationManager
    {
        return new InvitationManager(
            $repository ?? $this->createStub(InvitationRepository::class),
            $mailer ?? $this->createStub(MailerInterface::class),
            $this->createStub(TranslatorInterface::class),
            new RecordingDispatcher(),
        );
    }

    private function repositoryFinding(?Invitation $invitation): InvitationRepository
    {
        $repository = $this->createStub(InvitationRepository::class);
        $repository->method('findOneByPlainToken')->willReturn($invitation);

        return $repository;
    }
}
