<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Response factory for the mocked HTTP client in tests: remembers every request and answers 200 {"ok":true},
 * or what a test registered for the URL with respond().
 */
final class RecordingHttpClient
{
    /** @var list<array{method: string, url: string, body: string}> */
    public static array $requests = [];

    /** @var array<string, array{body: string, status: int}> URL substring => response */
    private static array $responses = [];

    /**
     * @return list<array{method: string, url: string, body: string}>
     */
    public static function requests(): array
    {
        return self::$requests;
    }

    /**
     * Requests whose URL contains the given text.
     *
     * @return list<array{method: string, url: string, body: string}>
     */
    public static function requestsTo(string $urlPart): array
    {
        return array_values(array_filter(self::$requests, static fn (array $r): bool => str_contains($r['url'], $urlPart)));
    }

    /**
     * @param array<string, mixed>|string $body
     */
    public static function respond(string $urlPart, array|string $body, int $status = 200): void
    {
        self::$responses[$urlPart] = ['body' => \is_array($body) ? (string) json_encode($body) : $body, 'status' => $status];
    }

    public static function reset(): void
    {
        self::$requests = [];
        self::$responses = [];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        self::$requests[] = ['method' => $method, 'url' => $url, 'body' => (string) ($options['body'] ?? '')];

        foreach (self::$responses as $part => $response) {
            if (str_contains($url, $part)) {
                return new MockResponse($response['body'], ['http_code' => $response['status']]);
            }
        }

        return new MockResponse('{"ok":true}', ['http_code' => 200]);
    }
}
