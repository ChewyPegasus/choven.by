<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\SiteSettings;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * With "admin_2fa_required" on, administrators must enable two-factor authentication
 * before they can open the admin panel.
 */
final class AdminTwoFactorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly SiteSettings $settings,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // After the firewall (priority 8) has authenticated the user.
        return [KernelEvents::REQUEST => ['onRequest', 4]];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        if (!$event->isMainRequest() || !str_starts_with($route, 'app_admin')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isAdmin() || $user->isTotpAuthenticationEnabled() || !$this->settings->isAdmin2faRequired()) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('warning', 'twofa.flash.admin_required');
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_profile_security', ['_locale' => $request->getLocale()]).'#two-factor'));
    }
}
