<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\CacheableTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AboutController extends AbstractController
{
    use CacheableTrait;

    #[Route('/about', name: 'app_about')]
    public function index(): Response
    {
        $galleryPath = $this->getParameter('kernel.project_dir') . '/assets/images/gallery';
        $images = [];

        if (is_dir($galleryPath)) {
            $finder = new Finder();
            $finder->files()->in($galleryPath)->sortByName();

            foreach ($finder as $file) {
                $images[] = 'images/gallery/' . $file->getRelativePathname();
            }
        }
        return $this->createCacheableResponse('about/index.html.twig', [
            'gallery_images' => $images,
        ]);
    }
}
