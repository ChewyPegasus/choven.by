<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LocaleController extends AbstractController
{
    #[Route('/change-locale/{locale}', name: 'app_change_locale')]
    public function changeLocale(string $locale, Request $request): Response
    {
        // Check that the locale is allowed
        if (!in_array($locale, ['ru', 'be', 'en'])) {
            $locale = 'ru';
        }
        
        // Get the previous URL
        $referer = $request->headers->get('referer');
        
        if ($referer) {
            // If referer exists, save it
            $url = $referer;
            
            // Parse the URL
            $parts = parse_url($url);
            
            if (isset($parts['path'])) {
                $path = $parts['path'];
                
                // Find the current locale in the URL and replace it with the new one
                $pattern = '~^/(' . implode('|', ['ru', 'be', 'en']) . ')/~';
                if (preg_match($pattern, $path, $matches)) {
                    $newPath = preg_replace($pattern, '/' . $locale . '/', $path);
                    $parts['path'] = $newPath;
                    
                    // Rebuild the URL
                    $url = $this->buildUrl($parts);
                    
                    return $this->redirect($url);
                }
            }
        }
        
        // If unable to determine the redirect URL, redirect to the homepage with the new locale
        return $this->redirectToRoute('app_main', ['_locale' => $locale]);
    }
    
    private function buildUrl(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        
        return sprintf('%s%s%s%s%s%s%s%s', 
        $scheme, 
        $user, 
        $pass, 
        $host, 
        $port, 
        $path, 
        $query, 
        $fragment);
        #return "$scheme$user$pass$host$port$path$query$fragment";
    }
}

