<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\HttpCache\AppHttpCache;
use App\Tests\Support\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

final class PageCacheTest extends WebTestCase
{
    public function testPublicPagesAreCacheableOnlyForAnonymousVisitors(): void
    {
        $this->client->request('GET', '/ru/');
        $cacheControl = (string) $this->client->getResponse()->headers->get('Cache-Control');
        self::assertStringContainsString('public', $cacheControl);
        self::assertStringContainsString('s-maxage=600', $cacheControl);
        self::assertStringContainsString('max-age=0', $cacheControl);

        // Personal pages are never shared.
        $this->client->request('GET', '/ru/booking');
        self::assertStringNotContainsString('public', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $this->client->loginUser($this->factory->user());
        $this->client->request('GET', '/ru/');
        $cacheControl = (string) $this->client->getResponse()->headers->get('Cache-Control');
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringNotContainsString('s-maxage', $cacheControl);
    }

    public function testHttpCacheServesAnonymousVisitorsAndSkipsSessions(): void
    {
        $kernel = static::getContainer()->get(KernelInterface::class);
        $dir = sys_get_temp_dir().'/choven-http-cache-'.bin2hex(random_bytes(4));
        $cache = new AppHttpCache($kernel, $dir, null, ['trace_level' => 'short']);

        try {
            $first = $cache->handle(Request::create('http://localhost/ru/about'));
            self::assertSame(200, $first->getStatusCode());
            self::assertStringContainsString('miss', (string) $first->headers->get('X-Symfony-Cache'));

            $second = $cache->handle(Request::create('http://localhost/ru/about'));
            self::assertStringContainsString('fresh', (string) $second->headers->get('X-Symfony-Cache'));
            self::assertSame($first->getContent(), $second->getContent());

            // Analytics cookies do not matter, a session cookie does.
            $withAnalytics = Request::create('http://localhost/ru/about', cookies: ['_ym_uid' => '123']);
            self::assertStringContainsString('fresh', (string) $cache->handle($withAnalytics)->headers->get('X-Symfony-Cache'));

            $withSession = Request::create('http://localhost/ru/about', cookies: ['PHPSESSID' => 'abc']);
            self::assertStringNotContainsString('fresh', (string) $cache->handle($withSession)->headers->get('X-Symfony-Cache'), 'rendered by the application, not taken from the cache');
        } finally {
            (new Filesystem())->remove($dir);
        }
    }

    public function testContentChangesEmptyTheCache(): void
    {
        $dir = static::getContainer()->getParameter('kernel.share_dir').'/http_cache';
        (new Filesystem())->dumpFile($dir.'/md/test', 'cached');

        $this->client->loginUser($this->factory->admin());
        $crawler = $this->client->request('GET', '/ru/admin/faq/1/edit');
        $this->submit($crawler, 'Сохранить', ['faq_item[position]' => '42']);

        self::assertDirectoryDoesNotExist($dir);
    }
}
