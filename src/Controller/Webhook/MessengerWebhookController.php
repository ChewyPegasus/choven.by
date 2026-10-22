<?php

declare(strict_types=1);

namespace App\Controller\Webhook;

use App\Messenger\BotUpdateHandler;
use App\Messenger\TelegramBot;
use App\Messenger\ViberBot;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Updates from the Telegram and Viber bots (customers connecting notifications).
 */
#[AsController]
final class MessengerWebhookController
{
    public function __construct(
        private readonly BotUpdateHandler $handler,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/webhook/telegram', name: 'app_webhook_telegram', methods: ['POST'])]
    public function telegram(Request $request, TelegramBot $bot): Response
    {
        if (!$bot->isEnabled() || !$bot->isValidWebhookSecret($request->headers->get('X-Telegram-Bot-Api-Secret-Token'))) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $update = $this->decode($request);
        $reply = null !== $update ? $this->handle(fn () => $this->handler->handleTelegram($update)) : null;

        // Answering with a method call saves a request to the Bot API.
        return null !== $reply
            ? new JsonResponse(['method' => 'sendMessage', 'chat_id' => $reply['chat_id'], 'text' => $reply['text'], 'parse_mode' => 'HTML'])
            : new Response('', Response::HTTP_OK);
    }

    #[Route('/webhook/viber', name: 'app_webhook_viber', methods: ['POST'])]
    public function viber(Request $request, ViberBot $bot): Response
    {
        if (!$bot->isEnabled() || !$bot->isValidSignature($request->getContent(), $request->headers->get('X-Viber-Content-Signature'))) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $event = $this->decode($request);
        $answer = null !== $event ? $this->handle(fn () => $this->handler->handleViber($event)) : null;

        return null !== $answer ? new JsonResponse($answer) : new Response('', Response::HTTP_OK);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(Request $request): ?array
    {
        try {
            $data = json_decode($request->getContent(), true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        /** @var array<string, mixed>|null */
        return \is_array($data) ? $data : null;
    }

    /**
     * The bot platforms resend failed updates forever: log errors and answer 200 anyway.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T|null
     */
    private function handle(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->logger->error('Bot update failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return null;
        }
    }
}
