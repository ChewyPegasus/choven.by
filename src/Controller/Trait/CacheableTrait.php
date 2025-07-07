<?php

declare(strict_types=1);

namespace App\Controller\Trait;

use Symfony\Component\HttpFoundation\Response;

trait CacheableTrait
{
    protected function createCacheableResponse(
        string $view, 
        array $parameters = [], 
        int $maxAge = 3600
    ): Response {
        $response = $this->render($view, $parameters);
        $response->setSharedMaxAge($maxAge);
        $response->setPublic();
        
        return $response;
    }
}
