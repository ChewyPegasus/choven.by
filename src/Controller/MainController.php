<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PackageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MainController extends AbstractController
{
    #[Route('/', name: 'app_main')]
    public function index(PackageService $packageService): Response
    {
        $packages = $packageService->getAllPackages();

        $response = $this->render('main/index.html.twig', [
            'packages' => $packages,
        ]);

        $response->setSharedMaxAge(3600);
        $response->setPublic();
        
        return $response;
    }
}
