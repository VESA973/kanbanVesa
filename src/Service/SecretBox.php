<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Symmetric encryption (libsodium secretbox) for secrets stored in the database,
 * such as the SMTP password. The key is derived from APP_SECRET: changing
 * APP_SECRET makes stored secrets unreadable (they must be entered again).
 */
final readonly class SecretBox
{
    private string $key;

    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        string $appSecret,
    ) {
        $this->key = hash('sha256', 'taskboard-secret-box|'.$appSecret, true);
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plaintext, $nonce, $this->key));
    }

    /**
     * @throws \RuntimeException when the value was not encrypted with the current key
     */
    public function decrypt(string $encrypted): string
    {
        $decoded = base64_decode($encrypted, true);
        if (false === $decoded || \strlen($decoded) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Invalid encrypted value.');
        }

        $plaintext = sodium_crypto_secretbox_open(
            substr($decoded, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($decoded, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key,
        );
        if (false === $plaintext) {
            throw new \RuntimeException('The secret cannot be decrypted (APP_SECRET changed?).');
        }

        return $plaintext;
    }
}
