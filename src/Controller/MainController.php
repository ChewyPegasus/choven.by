<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\CacheableTrait;
use App\Service\MapService;
use App\Service\PackageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for the main application pages.
 *
 * This controller handles requests for the application's homepage and
 * aggregates data related to packages and map routes for display.
 * It utilizes the CacheableTrait to ensure the response can be cached.
 */
final class MainController extends AbstractController
{
    use CacheableTrait;

    /**
     * Renders the main homepage of the application.
     *
     * This method fetches all available packages and map routes, including
     * their JSON representations and translated labels, to prepare data
     * for the main landing page. The response is configured to be cacheable.
     *
     * @param PackageService $packageService The service for retrieving package data.
     * @param MapService $mapService The service for retrieving map route data and translations.
     * @return Response The HTTP response for the main page.
     */
    #[Route('/', name: 'app_main')]
    public function index(
        PackageService $packageService,
        MapService $mapService,
    ): Response 
    {
        $packages = $packageService->getAllPackages();
        $routes = $mapService->getAllRoutes();
        $routesJson = $mapService->getRoutesForJson();
        $labels = $mapService->getTranslatedLabels();
        $labelsJson = json_encode($labels, JSON_UNESCAPED_UNICODE);

        return $this->createCacheableResponse('main/index.html.twig', [
            'packages' => $packages,
            'routes' => $routes,
            'routesJson' => $routesJson,
            'labels' => $labels,
            'labelsJson' => $labelsJson,
        ]);
    }
}