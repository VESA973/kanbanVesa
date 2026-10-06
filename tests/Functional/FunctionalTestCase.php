<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

abstract class FunctionalTestCase extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    /**
     * Login throttling is stored in a persistent cache: without this, failed
     * attempts from one test would lock out the users of the next ones.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $rateLimiterCache = self::getContainer()->get('cache.rate_limiter');
        self::assertInstanceOf(CacheItemPoolInterface::class, $rateLimiterCache);
        $rateLimiterCache->clear();

        self::ensureKernelShutdown();
    }

    protected static function extractLink(?object $email): string
    {
        self::assertInstanceOf(Email::class, $email);
        if (1 !== preg_match('/href="([^"]+)"/', (string) $email->getHtmlBody(), $matches)) {
            self::fail('The email must contain a link.');
        }

        return html_entity_decode($matches[1]);
    }
}
