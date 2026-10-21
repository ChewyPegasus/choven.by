<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\CacheableTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for displaying general information.
 *
 * This controller handles requests for the '/info' route and renders a static
 * information page. It utilizes the CacheableTrait to ensure the response can be cached.
 */
final class InfoController extends AbstractController
{
    use CacheableTrait;

    /**
     * Renders the general information page.
     *
     * This method simply renders the 'info/index.html.twig' template.
     * The response is configured to be cacheable using the CacheableTrait,
     * which sets appropriate HTTP caching headers.
     *
     * @return Response The HTTP response for the information page.
     */
    #[Route('/info', name: 'app_info')]
    public function index(): Response
    {
        return $this->createCacheableResponse('info/index.html.twig');
    }
}