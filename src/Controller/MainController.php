<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\CacheableTrait;
use App\Service\PackageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MainController extends AbstractController
{
    use CacheableTrait;

    #[Route('/', name: 'app_main')]
    public function index(PackageService $packageService): Response
    {
        $packages = $packageService->getAllPackages();

        return $this->createCacheableResponse('main/index.html.twig', [
            'packages' => $packages,
        ]);
    }
}
