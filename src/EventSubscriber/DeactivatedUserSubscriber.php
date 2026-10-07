<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Logs out, at their next request, a user whose account an administrator deactivated.
 * Priority 7: right after the firewall (8) has loaded the user from the database.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final readonly class DeactivatedUserSubscriber
{
    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $user = $this->security->getUser();
        if (!$event->isMainRequest() || !$user instanceof User || $user->isActive()) {
            return;
        }

        $this->security->logout(false);
        $session = $event->getRequest()->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', 'auth.account_disabled');
        }
        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login')));
    }
}
