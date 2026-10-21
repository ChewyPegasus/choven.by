<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Mime\Email;

/**
 * Read/retry/delete access to the Messenger "failed" transport (emails that could not be sent
 * after all automatic retries), for the admin panel.
 */
class FailedMessages
{
    public function __construct(
        #[Autowire(service: 'messenger.transport.failed')]
        private readonly ReceiverInterface $receiver,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function count(): int
    {
        try {
            return $this->receiver instanceof MessageCountAwareInterface ? $this->receiver->getMessageCount() : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return list<array{id: string, subject: string, to: string, error: string|null, failed_at: \DateTimeInterface|null, retries: int}>
     */
    public function all(int $limit = 100): array
    {
        if (!$this->receiver instanceof ListableReceiverInterface) {
            return [];
        }

        $result = [];
        foreach ($this->receiver->all($limit) as $envelope) {
            $result[] = $this->describe($envelope);
        }

        return $result;
    }

    public function retry(string $id): bool
    {
        $envelope = $this->find($id);
        if (null === $envelope) {
            return false;
        }

        $this->bus->dispatch($envelope->getMessage(), [new DelayStamp(0)]);
        $this->receiver->reject($envelope);

        return true;
    }

    public function delete(string $id): bool
    {
        $envelope = $this->find($id);
        if (null === $envelope) {
            return false;
        }

        $this->receiver->reject($envelope);

        return true;
    }

    private function find(string $id): ?Envelope
    {
        return $this->receiver instanceof ListableReceiverInterface ? $this->receiver->find($id) : null;
    }

    /**
     * @return array{id: string, subject: string, to: string, error: string|null, failed_at: \DateTimeInterface|null, retries: int}
     */
    private function describe(Envelope $envelope): array
    {
        $message = $envelope->getMessage();
        $subject = $message::class;
        $to = '';
        if ($message instanceof SendEmailMessage && $message->getMessage() instanceof Email) {
            $email = $message->getMessage();
            $subject = (string) $email->getSubject();
            $to = implode(', ', array_map(static fn ($a) => $a->getAddress(), $email->getTo()));
        }

        /** @var ErrorDetailsStamp|null $error */
        $error = $envelope->last(ErrorDetailsStamp::class);
        /** @var RedeliveryStamp|null $redelivery */
        $redelivery = $envelope->last(RedeliveryStamp::class);
        /** @var TransportMessageIdStamp|null $idStamp */
        $idStamp = $envelope->last(TransportMessageIdStamp::class);

        return [
            'id' => (string) $idStamp?->getId(),
            'subject' => $subject,
            'to' => $to,
            'error' => $error?->getExceptionMessage(),
            'failed_at' => $redelivery?->getRedeliveredAt(),
            'retries' => \count($envelope->all(RedeliveryStamp::class)),
        ];
    }
}
