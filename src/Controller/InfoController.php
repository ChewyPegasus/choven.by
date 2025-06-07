<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InfoController extends AbstractController
{
    #[Route('/info', name: 'app_info')]
    public function index(): Response
    {
        $response = $this->render('info/index.html.twig');

        $response->setSharedMaxAge(3600);
        $response->setPublic();

        return $response;
    }
}
