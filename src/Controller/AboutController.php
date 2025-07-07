<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\CacheableTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AboutController extends AbstractController
{
    use CacheableTrait;

    #[Route('/about', name: 'app_about')]
    public function index(): Response
    {
        return $this->createCacheableResponse('about/index.html.twig');
    }
}
