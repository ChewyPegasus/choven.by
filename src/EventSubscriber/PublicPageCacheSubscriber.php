<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\HttpCache\AppHttpCache;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Lets the full-page cache (App\HttpCache\AppHttpCache) keep public pages for anonymous visitors.
 *
 * Browsers always revalidate (max-age=0), the shared cache keeps the page for 10 minutes; any change
 * of the content in the admin panel empties the cache right away (App\EventListener\PageCacheInvalidator).
 */
final class PublicPageCacheSubscriber implements EventSubscriberInterface
{
    /** Pages that look the same for every anonymous visitor. */
    private const ROUTES = [
        'app_main', 'app_info', 'app_about', 'app_privacy', 'app_terms', 'app_tour_show',
        'app_faq', 'app_blog', 'app_blog_post', 'app_gift',
    ];
    private const SHARED_MAX_AGE = 600;

    public function __construct(private readonly Security $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Before the session listener (-1000), which makes the response private if a session was started.
        return [KernelEvents::RESPONSE => ['onResponse', -100]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$event->isMainRequest()
            || !$request->isMethodCacheable()
            || 200 !== $response->getStatusCode()
            || !\in_array($request->attributes->get('_route'), self::ROUTES, true)
            || AppHttpCache::isPersonal($request)
            || null !== $this->security->getUser()
            || [] !== $response->headers->getCookies()
            || ($request->hasSession() && $request->getSession()->isStarted())
            || [] !== array_diff(array_keys($request->query->all()), ['page'])) {
            return;
        }

        $response->setPublic();
        $response->setMaxAge(0);
        $response->setSharedMaxAge(self::SHARED_MAX_AGE);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->headers->addCacheControlDirective('stale-while-revalidate', '60');
        // No session was started and no cookie was sent, so nothing personal is on the page: the session
        // listener must not turn the response private just because a listener peeked into the session.
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
    }
}
