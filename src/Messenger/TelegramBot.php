<?php

declare(strict_types=1);

namespace App\Messenger;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Thin client for the Telegram Bot API (one bot for the team chat and for customers).
 */
class TelegramBot
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(default::TELEGRAM_BOT_TOKEN)%')]
        private readonly ?string $token = null,
        #[Autowire('%env(default::TELEGRAM_BOT_USERNAME)%')]
        private readonly ?string $username = null,
        #[Autowire('%env(default::TELEGRAM_WEBHOOK_SECRET)%')]
        private readonly ?string $webhookSecret = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return null !== $this->token && '' !== $this->token;
    }

    /**
     * Customers can connect only when the bot's public username is known.
     */
    public function canLinkCustomers(): bool
    {
        return $this->isEnabled() && null !== $this->username && '' !== $this->username;
    }

    public function getDeepLink(string $payload): string
    {
        return \sprintf('https://t.me/%s?start=%s', ltrim((string) $this->username, '@'), rawurlencode($payload));
    }

    public function isValidWebhookSecret(?string $secret): bool
    {
        return null !== $this->webhookSecret && '' !== $this->webhookSecret && null !== $secret && hash_equals($this->webhookSecret, $secret);
    }

    public function getWebhookSecret(): ?string
    {
        return $this->webhookSecret;
    }

    /**
     * Throws on a non-2xx answer (so that Messenger retries).
     */
    public function sendMessage(string $chatId, string $text, ?string $buttonText = null, ?string $buttonUrl = null): ResponseInterface
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];
        if (null !== $buttonText && null !== $buttonUrl && str_starts_with($buttonUrl, 'https://')) {
            // Telegram accepts only https links in inline buttons.
            $payload['reply_markup'] = ['inline_keyboard' => [[['text' => $buttonText, 'url' => $buttonUrl]]]];
        }

        $response = $this->call('sendMessage', $payload);
        $response->getHeaders(); // throws for 4xx/5xx

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function setWebhook(string $url): array
    {
        return $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $this->webhookSecret,
            'allowed_updates' => ['message'],
            'drop_pending_updates' => true,
        ])->toArray(false);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteWebhook(): array
    {
        return $this->call('deleteWebhook', [])->toArray(false);
    }

    /**
     * @return array<string, mixed>
     */
    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo', [])->toArray(false);
    }

    /**
     * Long polling, for local development where Telegram cannot reach a webhook.
     *
     * @return list<array<string, mixed>>
     */
    public function getUpdates(int $offset, int $timeout = 25): array
    {
        $data = $this->call('getUpdates', ['offset' => $offset, 'timeout' => $timeout, 'allowed_updates' => ['message']], $timeout + 10)->toArray(false);

        /** @var list<array<string, mixed>> */
        return \is_array($data['result'] ?? null) ? array_values($data['result']) : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function call(string $method, array $payload, int $timeout = 10): ResponseInterface
    {
        if (!$this->isEnabled()) {
            throw new \LogicException('TELEGRAM_BOT_TOKEN is not configured.');
        }

        return $this->httpClient->request('POST', \sprintf('https://api.telegram.org/bot%s/%s', $this->token, $method), [
            'json' => $payload,
            'timeout' => $timeout,
        ]);
    }
}
