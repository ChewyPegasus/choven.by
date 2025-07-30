<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscriber for setting the application locale based on URL, session, or default.
 *
 * This subscriber listens to the `KernelEvents::REQUEST` event to determine
 * and set the current locale for the request. It prioritizes the locale from
 * the URL, then the session, and finally falls back to a configured default locale.
 */
class LocaleSubscriber implements EventSubscriberInterface
{
    /**
     * @var string The default locale to use if no other locale is found.
     */
    private string $defaultLocale;

    /**
     * Constructs a new LocaleSubscriber instance.
     *
     * @param string $defaultLocale The default locale for the application. Defaults to 'ru'.
     */
    public function __construct(string $defaultLocale = 'ru')
    {
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * Sets the request locale based on available sources.
     *
     * This method is triggered on the `KernelEvents::REQUEST` event.
     * It checks for a locale in the request attributes (from the URL),
     * then in the session, and finally applies the default locale if none are found.
     * If a locale is found in the URL, it is also stored in the session.
     *
     * @param RequestEvent $event The event object containing the current request.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Check if locale exists in URL attributes (e.g., from routing)
        if ($locale = $request->attributes->get('_locale')) {
            $request->getSession()->set('_locale', $locale); // Store in session
        } 
        // If no locale in URL, try to get it from the session
        elseif ($locale = $request->getSession()->get('_locale')) {
            $request->setLocale($locale); // Set locale from session
        } else {
            // If no locale in URL or session, use the configured default locale
            $request->setLocale($this->defaultLocale);
        }
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array<string, array<array<string|int>>> The event names and their configurations.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // Listen to the KernelEvents::REQUEST event with a priority of 20
            // (a higher priority means it runs earlier).
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}