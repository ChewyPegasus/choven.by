<?php

declare(strict_types=1);

namespace App\Controller;

use App\Payment\PaymentGateways;
use App\Repository\ReviewRepository;
use App\Repository\TourRepository;
use App\Service\MapDataProvider;
use App\Service\SiteSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TourController extends AbstractController
{
    #[Route('/tours/{slug}', name: 'app_tour_show', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(
        string $slug,
        Request $request,
        TourRepository $tours,
        ReviewRepository $reviews,
        MapDataProvider $mapData,
        SiteSettings $settings,
        PaymentGateways $payments,
    ): Response {
        $tour = $tours->findOneActiveBySlug($slug);
        if (null === $tour) {
            throw $this->createNotFoundException();
        }

        $others = array_values(array_filter($tours->findActive(), static fn ($t) => $t !== $tour));

        return $this->render('tour/show.html.twig', [
            'tour' => $tour,
            'map_routes' => null !== $tour->getRoute() && $tour->getRoute()->isActive()
                ? [$mapData->export($tour->getRoute(), $request->getLocale())]
                : [],
            'other_tours' => \array_slice($others, 0, 3),
            'rating' => $reviews->getRatingSummary($tour),
            'prepayment_percent' => $payments->isEnabled() ? $settings->getPrepaymentPercent() : 0,
        ]);
    }
}
