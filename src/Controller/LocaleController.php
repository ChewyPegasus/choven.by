<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for handling locale changes in the application.
 *
 * This controller provides an endpoint to switch the application's language
 * and attempts to redirect the user back to their previous page with the new locale.
 */
class LocaleController extends AbstractController
{
    /**
     * Changes the application locale and redirects the user.
     *
     * This method takes a locale string, validates it against a predefined list
     * ('ru', 'be', 'en'), and then attempts to redirect the user back to the
     * page they came from, with the URL updated to reflect the new locale.
     * If the referer URL is not available or cannot be parsed, it redirects
     * to the main page with the new locale.
     *
     * @param string $locale The desired locale (e.g., 'en', 'ru', 'be').
     * @param Request $request The current HTTP request.
     * @return Response A RedirectResponse to the updated URL or the main page.
     */
    #[Route('/change-locale/{locale}', name: 'app_change_locale')]
    public function changeLocale(string $locale, Request $request): Response
    {
        // Check that the locale is allowed, default to 'ru' if not
        if (!in_array($locale, ['ru', 'be', 'en'])) {
            $locale = 'ru';
        }
        
        // Get the previous URL from the 'Referer' header
        $referer = $request->headers->get('referer');
        
        if ($referer) {
            $url = $referer;
            
            // Parse the URL into its components
            $parts = parse_url($url);
            
            // If a path exists in the parsed URL
            if (isset($parts['path'])) {
                $path = $parts['path'];
                
                // Define a regex pattern to find and replace the current locale in the path
                $pattern = '~^/(' . implode('|', ['ru', 'be', 'en']) . ')/~';
                if (preg_match($pattern, $path, $matches)) {
                    // Replace the old locale segment with the new one
                    $newPath = preg_replace($pattern, '/' . $locale . '/', $path);
                    $parts['path'] = $newPath;
                    
                    // Rebuild the URL with the updated path
                    $url = $this->buildUrl($parts);
                    
                    return $this->redirect($url);
                }
            }
        }
        
        // If unable to determine the redirect URL (e.g., no referer or parsing failed),
        // redirect to the homepage with the new locale.
        return $this->redirectToRoute('app_main', ['_locale' => $locale]);
    }
    
    /**
     * Reconstructs a URL from its parsed components.
     *
     * This helper method takes an associative array of URL components (as returned by parse_url())
     * and reconstructs a complete URL string.
     *
     * @param array<string, string|int> $parts An associative array of URL components.
     * @return string The reconstructed URL string.
     */
    private function buildUrl(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : ''; // Add '@' if user or pass exists
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        
        // Use sprintf for better readability and concatenation
        return sprintf('%s%s%s%s%s%s%s%s', 
            $scheme, 
            $user, 
            $pass, 
            $host, 
            $port, 
            $path, 
            $query, 
            $fragment
        );
    }
}