<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\CacheableTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for the "About Us" page.
 *
 * This controller handles requests for the '/about' route, renders the
 * about page, and includes dynamic content such as images from a gallery directory.
 * It utilizes the CacheableTrait to ensure the response can be cached.
 */
final class AboutController extends AbstractController
{
    use CacheableTrait;

    /**
     * Renders the about page, including gallery images.
     *
     * This method retrieves image file paths from the 'assets/images/gallery' directory,
     * sorts them by name, and passes them to the 'about/index.html.twig' template.
     * The response is configured to be cacheable using the CacheableTrait.
     *
     * @return Response The HTTP response for the about page.
     */
    #[Route('/about', name: 'app_about')]
    public function index(): Response
    {
        $galleryPath = $this->getParameter('kernel.project_dir') . '/assets/images/gallery';
        $images = [];

        // Check if the gallery directory exists before attempting to find files
        if (is_dir($galleryPath)) {
            $finder = new Finder();
            // Find all files within the gallery directory and sort them by name
            $finder->files()->in($galleryPath)->sortByName();

            foreach ($finder as $file) {
                // Add the relative path to the image, suitable for web access
                $images[] = 'images/gallery/' . $file->getRelativePathname();
            }
        }

        // Render the Twig template and make the response cacheable
        return $this->createCacheableResponse('about/index.html.twig', [
            'gallery_images' => $images,
        ]);
    }
}