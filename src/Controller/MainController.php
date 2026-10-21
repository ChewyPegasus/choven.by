<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContactRequest;
use App\Enum\Package;
use App\Form\ContactRequestType;
use App\Repository\ReviewRepository;
use App\Repository\TourRepository;
use App\Service\MapDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MainController extends AbstractController
{
    #[Route('', name: 'app_main', methods: ['GET'])]
    public function index(
        Request $request,
        TourRepository $tours,
        ReviewRepository $reviews,
        MapDataProvider $mapData,
    ): Response {
        $contactForm = $this->createForm(ContactRequestType::class, new ContactRequest(), [
            'action' => $this->generateUrl('app_contact'),
        ]);

        return $this->render('main/index.html.twig', [
            'tours' => $tours->findActive(),
            'packages' => Package::showcase(),
            'map_routes' => $mapData->getRoutes($request->getLocale()),
            'reviews' => $reviews->findPublished(6),
            'rating' => $reviews->getRatingSummary(),
            'contact_form' => $contactForm,
        ]);
    }

    #[Route('/info', name: 'app_info', methods: ['GET'])]
    public function info(): Response
    {
        return $this->render('info/index.html.twig');
    }

    #[Route('/about', name: 'app_about', methods: ['GET'])]
    public function about(): Response
    {
        $galleryPath = $this->getParameter('kernel.project_dir').'/assets/images/gallery';
        $images = [];
        if (is_dir($galleryPath)) {
            foreach (new \FilesystemIterator($galleryPath) as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isFile() && \in_array(mb_strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $images[] = 'images/gallery/'.$file->getFilename();
                }
            }
            sort($images);
        }

        return $this->render('about/index.html.twig', ['gallery_images' => $images]);
    }

    #[Route('/privacy', name: 'app_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig');
    }

    #[Route('/terms', name: 'app_terms', methods: ['GET'])]
    public function terms(): Response
    {
        return $this->render('legal/terms.html.twig');
    }
}
