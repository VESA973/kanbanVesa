<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class EmailVerifierTest extends TestCase
{
    public function testSendsASignedLinkToTheUser(): void
    {
        $helper = new FakeVerifyEmailHelper();
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send')->with($this->callback(
            static fn (TemplatedEmail $email): bool => 'camille@example.com' === $email->getTo()[0]->getAddress()
                && 'https://kanban.lan/verify/email?signature=abc' === $email->getContext()['signedUrl'],
        ));

        $this->verifier($helper, $mailer)->sendVerificationEmail($this->persistedUser());

        self::assertSame([['userId' => '42', 'userEmail' => 'camille@example.com']], $helper->generatedSignatures);
    }

    public function testRefusesToSignALinkForAnUnsavedUser(): void
    {
        $this->expectException(\LogicException::class);

        $this->verifier()->sendVerificationEmail(new User('camille@example.com', 'Camille', 'Martin'));
    }

    public function testValidLinkMarksTheUserAsVerified(): void
    {
        $user = $this->persistedUser();
        $repository = $this->createMock(UserRepository::class);
        $repository->expects($this->once())->method('save')->with($user);

        $this->verifier(repository: $repository)->confirm(new Request(), $user);

        self::assertTrue($user->isVerified());
    }

    public function testInvalidLinkLeavesTheUserUnverified(): void
    {
        $user = $this->persistedUser();

        try {
            $this->verifier(new FakeVerifyEmailHelper(signatureIsValid: false))->confirm(new Request(), $user);
            self::fail('An invalid signature must be rejected.');
        } catch (VerifyEmailExceptionInterface) {
            self::assertFalse($user->isVerified());
        }
    }

    private function verifier(
        ?FakeVerifyEmailHelper $helper = null,
        ?MailerInterface $mailer = null,
        ?UserRepository $repository = null,
    ): EmailVerifier {
        return new EmailVerifier(
            $helper ?? new FakeVerifyEmailHelper(),
            $mailer ?? $this->createStub(MailerInterface::class),
            $this->createStub(TranslatorInterface::class),
            $repository ?? $this->createStub(UserRepository::class),
        );
    }

    private function persistedUser(): User
    {
        $user = new User('camille@example.com', 'Camille', 'Martin');
        new \ReflectionProperty(User::class, 'id')->setValue($user, 42);

        return $user;
    }
}
