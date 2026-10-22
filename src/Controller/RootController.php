<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Localized;
use App\Repository\FaqItemRepository;
use App\Repository\PostRepository;
use App\Repository\TourRepository;
use App\Service\Heartbeat;
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
            foreach (['admin', 'profile', 'booking/', 'gift/', 'payment/', '2fa', 'login', 'register', 'reset-password', 'verify'] as $path) {
                $disallow[] = 'Disallow: /'.$locale.'/'.$path;
            }
        }

        $response = new Response("User-agent: *\n".implode("\n", $disallow)."\n\nSitemap: ".$sitemap."\n");
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->setPublic()->setMaxAge(86400);

        return $response;
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET', 'HEAD'], format: 'xml')]
    public function sitemap(TourRepository $tours, PostRepository $posts, FaqItemRepository $faq): Response
    {
        $pages = [
            ['route' => 'app_main', 'params' => [], 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['route' => 'app_info', 'params' => [], 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['route' => 'app_about', 'params' => [], 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['route' => 'app_order_new', 'params' => [], 'priority' => '0.9', 'changefreq' => 'monthly'],
            ['route' => 'app_gift', 'params' => [], 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['route' => 'app_privacy', 'params' => [], 'priority' => '0.2', 'changefreq' => 'yearly'],
            ['route' => 'app_terms', 'params' => [], 'priority' => '0.2', 'changefreq' => 'yearly'],
        ];
        if ([] !== $faq->findActive()) {
            $pages[] = ['route' => 'app_faq', 'params' => [], 'priority' => '0.7', 'changefreq' => 'monthly'];
        }
        foreach ($tours->findActive() as $tour) {
            $pages[] = ['route' => 'app_tour_show', 'params' => ['slug' => $tour->getSlug()], 'priority' => '0.8', 'changefreq' => 'monthly'];
        }
        $latest = $posts->findLatest(500);
        if ([] !== $latest) {
            $pages[] = ['route' => 'app_blog', 'params' => [], 'priority' => '0.6', 'changefreq' => 'weekly'];
        }
        foreach ($latest as $post) {
            $pages[] = ['route' => 'app_blog_post', 'params' => ['slug' => $post->getSlug()], 'priority' => '0.6', 'changefreq' => 'monthly', 'lastmod' => $post->getUpdatedAt()];
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

    /**
     * Probe for the background worker (emails, reminders, scheduled jobs), for an uptime monitor:
     * 503 when the worker has not run for 15 minutes or the queue is stuck.
     */
    #[Route('/health/worker', name: 'app_health_worker', methods: ['GET', 'HEAD'])]
    public function workerHealth(Connection $connection, Heartbeat $heartbeat): JsonResponse
    {
        $lastBeat = $heartbeat->getLastBeat();
        $age = null !== $lastBeat ? time() - $lastBeat->getTimestamp() : null;

        try {
            /** @var array{pending: int|string, oldest: string|null, failed: int|string} $queue */
            $queue = $connection->fetchAssociative(
                "SELECT COUNT(*) FILTER (WHERE queue_name = 'default' AND delivered_at IS NULL) AS pending,
                        MIN(available_at) FILTER (WHERE queue_name = 'default' AND delivered_at IS NULL) AS oldest,
                        COUNT(*) FILTER (WHERE queue_name = 'failed') AS failed
                 FROM messenger_messages",
            ) ?: ['pending' => 0, 'oldest' => null, 'failed' => 0];
        } catch (\Throwable) {
            $queue = null;
        }

        $oldestAge = null !== $queue && null !== $queue['oldest'] ? max(0, time() - (new \DateTimeImmutable($queue['oldest']))->getTimestamp()) : null;
        $healthy = null !== $age && $age < 900 && null !== $queue && (null === $oldestAge || $oldestAge < 900);

        $response = new JsonResponse([
            'status' => $healthy ? 'ok' : 'degraded',
            'last_heartbeat_seconds_ago' => $age,
            'queue' => null !== $queue ? ['pending' => (int) $queue['pending'], 'oldest_seconds' => $oldestAge, 'failed' => (int) $queue['failed']] : null,
        ], $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
