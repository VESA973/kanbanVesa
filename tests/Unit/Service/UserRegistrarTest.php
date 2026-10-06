<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Form\Data\RegistrationData;
use App\Repository\UserRepository;
use App\Service\EmailVerifier;
use App\Service\UserRegistrar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class UserRegistrarTest extends TestCase
{
    public function testRegistersAUserWithAHashedPasswordAndSendsTheVerificationEmail(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects($this->once())->method('save')->willReturnCallback(
            static fn (User $user): null => new \ReflectionProperty(User::class, 'id')->setValue($user, 1),
        );
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed-password');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $emailVerifier = new EmailVerifier(new FakeVerifyEmailHelper(), $mailer, $this->createStub(TranslatorInterface::class), $repository);
        $user = new UserRegistrar($repository, $hasher, $emailVerifier)->register($this->registrationData());

        self::assertSame('camille@example.com', $user->getEmail());
        self::assertSame('Camille Martin', $user->getFullName());
        self::assertSame('hashed-password', $user->getPassword());
        self::assertFalse($user->isVerified());
    }

    private function registrationData(): RegistrationData
    {
        $data = new RegistrationData();
        $data->email = ' Camille@Example.com ';
        $data->firstName = 'Camille';
        $data->lastName = 'Martin';
        $data->plainPassword = 'motdepasse';

        return $data;
    }
}
