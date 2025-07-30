<?php

declare(strict_types=1);

namespace App\Authentication;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Authenticator for handling user logins.
 *
 * This class extends Symfony's AbstractLoginFormAuthenticator to provide
 * a custom authentication flow for users, including handling form submissions,
 * creating a passport, and redirecting upon successful authentication.
 */
class UserAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    /**
     * Constructs a new UserAuthenticator instance.
     *
     * @param UrlGeneratorInterface $urlGenerator The URL generator service for creating URLs.
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator, 
    ) {
    }

    /**
     * Authenticates the user based on the incoming request.
     *
     * This method is responsible for extracting credentials (email, password, CSRF token)
     * from the request and creating a Passport object.
     *
     * @param Request $request The current HTTP request.
     * @return Passport A Passport object containing the user's credentials and badges.
     */
    public function authenticate(Request $request): Passport
    {
        $email = (string) $request->request->get('email', '');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials((string) $request->request->get('password', '')),
            [
                new CsrfTokenBadge('authenticate', (string) $request->request->get('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    /**
     * Handles the response upon successful authentication.
     *
     * If a target path is set in the session (e.g., after trying to access a protected page),
     * the user is redirected to that path. Otherwise, they are redirected to the main application page.
     *
     * @param Request $request The current HTTP request.
     * @param TokenInterface $token The authenticated token.
     * @param string $firewallName The name of the firewall that handled the authentication.
     * @return Response|null A RedirectResponse object, or null if no redirect is necessary.
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_main'));
    }

    /**
     * Returns the URL to the login page.
     *
     * This method is required by AbstractLoginFormAuthenticator to determine
     * where to redirect unauthenticated users when they try to access a protected resource.
     *
     * @param Request $request The current HTTP request.
     * @return string The URL of the login page.
     */
    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}