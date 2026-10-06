<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PasswordResetter;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\TooManyPasswordRequestsException;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelper;

final class PasswordResetterTest extends TestCase
{
    public function testSendsAResetLinkToAKnownUser(): void
    {
        $token = $this->token('real-token');
        $helper = $this->createStub(ResetPasswordHelper::class);
        $helper->method('generateResetToken')->willReturn($token);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $result = $this->resetter($helper, $mailer, $this->repositoryFinding($this->user()))->sendResetLink('camille@example.com');

        self::assertSame($token, $result);
    }

    public function testUnknownEmailReturnsAFakeTokenWithoutSendingAnything(): void
    {
        $fakeToken = $this->token('fake-token');
        $helper = $this->createStub(ResetPasswordHelper::class);
        $helper->method('generateFakeResetToken')->willReturn($fakeToken);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $result = $this->resetter($helper, $mailer, $this->repositoryFinding(null))->sendResetLink('nobody@example.com');

        self::assertSame($fakeToken, $result);
    }

    public function testTooManyRequestsReturnsAFakeTokenWithoutSendingAnything(): void
    {
        $fakeToken = $this->token('fake-token');
        $helper = $this->createStub(ResetPasswordHelper::class);
        $helper->method('generateResetToken')->willThrowException(new TooManyPasswordRequestsException(new \DateTimeImmutable('+15 minutes')));
        $helper->method('generateFakeResetToken')->willReturn($fakeToken);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $result = $this->resetter($helper, $mailer, $this->repositoryFinding($this->user()))->sendResetLink('camille@example.com');

        self::assertSame($fakeToken, $result);
    }

    public function testResetPasswordConsumesTheTokenAndStoresTheNewHash(): void
    {
        $user = $this->user();
        $helper = $this->createMock(ResetPasswordHelper::class);
        $helper->expects($this->once())->method('removeResetRequest')->with('the-token');
        $repository = $this->createMock(UserRepository::class);
        $repository->expects($this->once())->method('save')->with($user);

        $this->resetter($helper, repository: $repository)->resetPassword('the-token', $user, 'nouveau-secret');

        self::assertSame('hashed-password', $user->getPassword());
    }

    private function resetter(
        ResetPasswordHelper $helper,
        ?MailerInterface $mailer = null,
        ?UserRepository $repository = null,
    ): PasswordResetter {
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed-password');

        return new PasswordResetter(
            $helper,
            $repository ?? $this->createStub(UserRepository::class),
            $hasher,
            $mailer ?? $this->createStub(MailerInterface::class),
            $this->createStub(TranslatorInterface::class),
        );
    }

    private function repositoryFinding(?User $user): UserRepository&Stub
    {
        $repository = $this->createStub(UserRepository::class);
        $repository->method('findOneByEmail')->willReturn($user);

        return $repository;
    }

    private function user(): User
    {
        return new User('camille@example.com', 'Camille', 'Martin');
    }

    private function token(string $value): ResetPasswordToken
    {
        return new ResetPasswordToken($value, new \DateTimeImmutable('+1 hour'), time());
    }
}
