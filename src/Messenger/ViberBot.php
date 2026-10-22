<?php

declare(strict_types=1);

namespace App\Messenger;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Viber Bot API (https://developers.viber.com/docs/api/rest-bot-api/).
 *
 * Note: since 2024 new Viber bots are available only under a commercial agreement with Rakuten Viber.
 */
class ViberBot
{
    private const API = 'https://chatapi.viber.com/pa/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(default::VIBER_AUTH_TOKEN)%')]
        private readonly ?string $token = null,
        /** Public account URI used in deep links: viber://pa?chatURI=<uri>. */
        #[Autowire('%env(default::VIBER_BOT_URI)%')]
        private readonly ?string $uri = null,
        #[Autowire('%env(default::VIBER_BOT_NAME)%')]
        private readonly ?string $name = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return null !== $this->token && '' !== $this->token && null !== $this->uri && '' !== $this->uri;
    }

    public function getDeepLink(string $payload): string
    {
        return \sprintf('viber://pa?chatURI=%s&context=%s', rawurlencode((string) $this->uri), rawurlencode($payload));
    }

    public function isValidSignature(string $body, ?string $signature): bool
    {
        if (null === $this->token || '' === $this->token || null === $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $this->token), strtolower($signature));
    }

    /**
     * Message structure accepted by send_message and as the answer to "conversation_started".
     *
     * @param list<array{text: string, url?: string, reply?: string}> $buttons
     *
     * @return array<string, mixed>
     */
    public function buildMessage(string $text, array $buttons = []): array
    {
        $message = [
            'min_api_version' => 3,
            'sender' => ['name' => mb_substr($this->name ?? 'Choven.by', 0, 28)],
            'type' => 'text',
            'text' => mb_substr($text, 0, 7000),
        ];

        if ([] !== $buttons) {
            $message['keyboard'] = [
                'Type' => 'keyboard',
                'DefaultHeight' => false,
                'Buttons' => array_map(static fn (array $button): array => isset($button['url'])
                    ? ['ActionType' => 'open-url', 'ActionBody' => $button['url'], 'Text' => $button['text'], 'Columns' => 6, 'Rows' => 1]
                    : ['ActionType' => 'reply', 'ActionBody' => $button['reply'] ?? $button['text'], 'Text' => $button['text'], 'Columns' => 6, 'Rows' => 1],
                    $buttons),
            ];
        }

        return $message;
    }

    /**
     * Throws on a transport error; returns false when Viber refused the message (e.g. the user unsubscribed).
     *
     * @param list<array{text: string, url?: string, reply?: string}> $buttons
     */
    public function sendMessage(string $receiver, string $text, array $buttons = []): bool
    {
        $data = $this->call('send_message', ['receiver' => $receiver] + $this->buildMessage($text, $buttons));

        return 0 === ($data['status'] ?? -1);
    }

    /**
     * @return array<string, mixed>
     */
    public function setWebhook(string $url): array
    {
        return $this->call('set_webhook', [
            'url' => $url,
            'event_types' => ['subscribed', 'unsubscribed', 'conversation_started', 'message'],
            'send_name' => true,
            'send_photo' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function call(string $method, array $payload): array
    {
        if (null === $this->token || '' === $this->token) {
            throw new \LogicException('VIBER_AUTH_TOKEN is not configured.');
        }

        return $this->httpClient->request('POST', self::API.$method, [
            'headers' => ['X-Viber-Auth-Token' => $this->token],
            'json' => $payload,
            'timeout' => 10,
        ])->toArray();
    }
}
