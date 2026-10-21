<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Controller for handling user authentication (login and logout).
 *
 * This controller provides the logic for displaying the login form,
 * processing authentication errors, and handling the logout process.
 */
final class AuthController extends AbstractController
{
    /**
     * Displays the login form and handles authentication errors.
     *
     * If the user is already authenticated, they are redirected to the main application page.
     * Otherwise, it retrieves the last authentication error and the last entered username
     * to pre-fill the form or display error messages.
     * The response is explicitly set to be non-cacheable to prevent caching of sensitive login pages.
     *
     * @param AuthenticationUtils $authenticationUtils Utility to get authentication-related data.
     * @return Response The HTTP response for the login page or a redirect.
     */
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If the user is already logged in, redirect to the main page
        if ($this->getUser()) {
            return $this->redirectToRoute('app_main');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Render the login form template
        $response = $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);

        // Prevent caching of the login page
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    /**
     * Handles the logout process.
     *
     * This method is intentionally left blank as Symfony's security component
     * intercepts requests to this route and handles the actual logout logic
     * based on the firewall configuration.
     *
     * @throws \LogicException This exception is thrown if the method is accidentally called,
     * as it should be intercepted by the security firewall.
     */
    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on firewall.');
    }
}