<?php

declare(strict_types=1);

namespace App\Mailer;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Enables MAILER_DSN=settings://default (autoconfigured as a mailer transport factory).
 */
final readonly class SettingsTransportFactory implements TransportFactoryInterface
{
    public function __construct(
        private SettingsTransport $transport,
    ) {
    }

    public function create(Dsn $dsn): TransportInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, 'settings', ['settings']);
        }

        return $this->transport;
    }

    public function supports(Dsn $dsn): bool
    {
        return 'settings' === $dsn->getScheme();
    }
}
