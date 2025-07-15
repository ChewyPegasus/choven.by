<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\CacheableTrait;
use App\Service\MapService;
use App\Service\PackageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MainController extends AbstractController
{
    use CacheableTrait;

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
