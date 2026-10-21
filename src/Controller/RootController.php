<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Localized;
use App\Repository\TourRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Routes that live outside of the /{_locale} prefix.
 */
final class RootController extends AbstractController
{
    /**
     * Sends visitors to the version of the site in their browser language.
     */
    #[Route('/', name: 'app_root', methods: ['GET', 'HEAD'])]
    public function root(Request $request): RedirectResponse
    {
        $locale = $request->getPreferredLanguage(Localized::LOCALES) ?? Localized::DEFAULT_LOCALE;

        $response = $this->redirectToRoute('app_main', ['_locale' => $locale]);
        $response->setVary('Accept-Language');

        return $response;
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET', 'HEAD'], format: 'txt')]
    public function robots(): Response
    {
        $sitemap = $this->generateUrl('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $disallow = [];
        foreach (Localized::LOCALES as $locale) {
            foreach (['admin', 'profile', 'booking/', 'login', 'register', 'reset-password', 'verify'] as $path) {
                $disallow[] = 'Disallow: /'.$locale.'/'.$path;
            }
        }

        $response = new Response("User-agent: *\n".implode("\n", $disallow)."\n\nSitemap: ".$sitemap."\n");
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->setPublic()->setMaxAge(86400);

        return $response;
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET', 'HEAD'], format: 'xml')]
    public function sitemap(TourRepository $tours): Response
    {
        $pages = [
            ['route' => 'app_main', 'params' => [], 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['route' => 'app_info', 'params' => [], 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['route' => 'app_about', 'params' => [], 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['route' => 'app_order_new', 'params' => [], 'priority' => '0.9', 'changefreq' => 'monthly'],
            ['route' => 'app_privacy', 'params' => [], 'priority' => '0.2', 'changefreq' => 'yearly'],
            ['route' => 'app_terms', 'params' => [], 'priority' => '0.2', 'changefreq' => 'yearly'],
        ];
        foreach ($tours->findActive() as $tour) {
            $pages[] = ['route' => 'app_tour_show', 'params' => ['slug' => $tour->getSlug()], 'priority' => '0.8', 'changefreq' => 'monthly'];
        }

        $response = $this->render('seo/sitemap.xml.twig', ['pages' => $pages, 'locales' => Localized::LOCALES]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->setPublic()->setMaxAge(3600);

        return $response;
    }

    /**
     * Liveness/readiness probe for Docker and uptime monitors.
     */
    #[Route('/health', name: 'app_health', methods: ['GET', 'HEAD'])]
    public function health(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');
            $database = 'ok';
        } catch (\Throwable) {
            $database = 'error';
        }

        $response = new JsonResponse(
            ['status' => 'ok' === $database ? 'ok' : 'degraded', 'database' => $database],
            'ok' === $database ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
