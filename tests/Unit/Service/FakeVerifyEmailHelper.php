<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use Symfony\Component\HttpFoundation\Request;
use SymfonyCasts\Bundle\VerifyEmail\Exception\InvalidSignatureException;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * The bundle helper is final and declares validateEmailConfirmationFromRequest()
 * only through @method, so it cannot be mocked by PHPUnit.
 */
final class FakeVerifyEmailHelper implements VerifyEmailHelperInterface
{
    /** @var list<array{userId: string, userEmail: string}> */
    public array $generatedSignatures = [];

    public function __construct(
        private readonly bool $signatureIsValid = true,
    ) {
    }

    /**
     * @param array<string, mixed> $extraParams
     */
    public function generateSignature(string $routeName, string $userId, string $userEmail, array $extraParams = []): VerifyEmailSignatureComponents
    {
        $this->generatedSignatures[] = ['userId' => $userId, 'userEmail' => $userEmail];

        return new VerifyEmailSignatureComponents(new \DateTimeImmutable('+1 hour'), 'https://kanban.lan/verify/email?signature=abc', time());
    }

    public function validateEmailConfirmation(string $signedUrl, string $userId, string $userEmail): void
    {
        throw new \BadMethodCallException('Deprecated, use validateEmailConfirmationFromRequest().');
    }

    public function validateEmailConfirmationFromRequest(Request $request, string $userId, string $userEmail): void
    {
        if (!$this->signatureIsValid) {
            throw new InvalidSignatureException();
        }
    }
}
