<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Response factory for the mocked HTTP client in tests: answers 200 and remembers every request.
 */
final class RecordingHttpClient
{
    /** @var list<array{method: string, url: string, body: string}> */
    public static array $requests = [];

    /**
     * @return list<array{method: string, url: string, body: string}>
     */
    public static function requests(): array
    {
        return self::$requests;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        self::$requests[] = ['method' => $method, 'url' => $url, 'body' => (string) ($options['body'] ?? '')];

        return new MockResponse('{"ok":true}', ['http_code' => 200]);
    }
}
