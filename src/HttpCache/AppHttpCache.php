<?php

declare(strict_types=1);

namespace App\HttpCache;

use Symfony\Bundle\FrameworkBundle\HttpCache\HttpCache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Full-page cache in front of the application (production only, see config/packages/framework.yaml).
 *
 * Anonymous visitors of public pages get cached HTML (App\EventSubscriber\PublicPageCacheSubscriber marks
 * which pages may be cached). Requests of signed-in users, or of anyone with a session (flash messages,
 * forms in progress), skip the cache completely, so personal pages are never shared.
 */
class AppHttpCache extends HttpCache
{
    /** Cookies that make a request personal. */
    public const PERSONAL_COOKIES = ['PHPSESSID', 'REMEMBERME', 'trusted_device'];

    public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): Response
    {
        if (HttpKernelInterface::MAIN_REQUEST === $type && self::isPersonal($request)) {
            return $this->pass($request, $catch);
        }

        return parent::handle($request, $type, $catch);
    }

    public static function isPersonal(Request $request): bool
    {
        foreach (self::PERSONAL_COOKIES as $cookie) {
            if ($request->cookies->has($cookie)) {
                return true;
            }
        }

        return $request->headers->has('Authorization');
    }
}
