<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PublicPagesTest extends WebTestCase
{
    /**
     * @return iterable<array{string}>
     */
    public static function pages(): iterable
    {
        foreach (['ru', 'be', 'en'] as $locale) {
            foreach (['/', '/info', '/about', '/booking', '/booking?tour=isloch-overnight&people=4', '/tours/isloch-overnight', '/contact', '/privacy', '/terms', '/login', '/register', '/reset-password', '/verify/resend'] as $path) {
                yield $locale.$path => ['/'.$locale.$path];
            }
        }
    }

    #[DataProvider('pages')]
    public function testPageIsAvailable(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('header.site-header');
        self::assertSelectorExists('footer.site-footer');
        self::assertSelectorExists('link[rel="canonical"], meta[name="robots"]');
    }

    public function testRootRedirectsToBrowserLanguage(): void
    {
        $this->client->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9']);
        self::assertResponseRedirects('/en/');

        $this->client->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'de-DE']);
        self::assertResponseRedirects('/ru/');
    }

    public function testHomePageShowsToursPackagesAndMap(): void
    {
        $crawler = $this->client->request('GET', '/ru/');

        self::assertCount(5, $crawler->filter('.tour-card'));
        self::assertCount(4, $crawler->filter('dialog.modal'));
        self::assertSelectorExists('[data-controller="map"]');
        self::assertSelectorExists('script[type="application/ld+json"]');
        self::assertSelectorCount(3, 'link[rel="alternate"][hreflang]:not([hreflang="x-default"])');
    }

    public function testAnonymousVisitorsDoNotGetASession(): void
    {
        $this->client->request('GET', '/ru/');

        self::assertResponseIsSuccessful();
        self::assertNull($this->client->getCookieJar()->get('PHPSESSID'));
    }

    public function testLanguageSwitcherKeepsThePage(): void
    {
        $crawler = $this->client->request('GET', '/ru/info');

        self::assertCount(1, $crawler->filter('a[hreflang="en"][href="/en/info"]'));
    }

    public function testUnknownPagesReturn404(): void
    {
        $this->client->request('GET', '/ru/tours/does-not-exist');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/de/');
        self::assertResponseStatusCodeSame(404);
    }

    public function testSeoFiles(): void
    {
        $this->client->request('GET', '/robots.txt');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        $xml = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('<loc>http://localhost/be/tours/isloch-overnight</loc>', $xml);
        self::assertStringContainsString('hreflang="en"', $xml);
    }

    public function testSpoofedForwardedHostIsIgnored(): void
    {
        $this->client->request('GET', '/robots.txt', server: ['HTTP_X_FORWARDED_HOST' => 'evil.example', 'REMOTE_ADDR' => '10.0.0.5']);

        self::assertStringNotContainsString('evil.example', (string) $this->client->getResponse()->getContent());
    }

    public function testHealthCheck(): void
    {
        $this->client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'ok', 'database' => 'ok'], json_decode((string) $this->client->getResponse()->getContent(), true));
    }

    public function testRestrictedAreasRequireLogin(): void
    {
        foreach (['/ru/profile/orders', '/ru/admin', '/ru/admin/orders'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('/ru/login');
        }
    }
}
