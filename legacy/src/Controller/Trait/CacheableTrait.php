<?php

declare(strict_types=1);

namespace App\Controller\Trait;

use Symfony\Component\HttpFoundation\Response;

/**
 * Trait CacheableTrait
 *
 * Provides a helper method for creating cacheable Symfony HTTP responses.
 * This trait is intended to be used within Symfony controllers (classes extending AbstractController)
 * to easily set up shared and public caching for rendered views.
 */
trait CacheableTrait
{
    /**
     * Creates a cacheable Symfony Response object for a rendered view.
     *
     * This method renders a Twig view with the given parameters and configures
     * the resulting Response object for shared and public caching with a specified max-age.
     *
     * @param string $view The name of the Twig template to render (e.g., 'template.html.twig').
     * @param array $parameters An associative array of parameters to pass to the view.
     * @param int $maxAge The maximum age (in seconds) that the response can be cached by shared caches (e.g., proxies, CDNs).
     * @return Response The configured cacheable Symfony Response object.
     */
    protected function createCacheableResponse(
        string $view, 
        array $parameters = [], 
        int $maxAge = 3600
    ): Response {
        // Assume 'render' method is available from AbstractController
        $response = $this->render($view, $parameters);
        
        // Set the maximum age for shared caches (e.g., CDN, proxy)
        $response->setSharedMaxAge($maxAge);
        
        // Mark the response as public, meaning it can be cached by any cache
        $response->setPublic();
        
        return $response;
    }
}