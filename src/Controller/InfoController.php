<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\CacheableTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InfoController extends AbstractController
{
    use CacheableTrait;

    #[Route('/info', name: 'app_info')]
    public function index(): Response
    {
        return $this->createCacheableResponse('info/index.html.twig');
    }
}
