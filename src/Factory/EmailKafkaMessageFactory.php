<?php

declare(strict_types=1);

namespace App\Factory;

use App\DTO\AbstractEmailDTO;
use App\DTO\OrderDTO;
use App\DTO\VerificationDTO;
use JsonException;
use Psr\Log\LoggerInterface;

/**
 * Service for creating Kafka message payloads specifically for email-related DTOs.
 *
 * This factory converts AbstractEmailDTO instances into a structured array,
 * which is then JSON-encoded to be suitable for publishing to Kafka.
 */
class EmailKafkaMessageFactory
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * Creates a JSON string payload from an AbstractEmailDTO for Kafka.
     *
     * @param AbstractEmailDTO $dto The data transfer object containing email-related information.
     * @return string The JSON-encoded string payload.
     * @throws JsonException If the DTO context cannot be JSON encoded.
     * @throws \InvalidArgumentException If the DTO type is not supported.
     */
    public function createPayload(AbstractEmailDTO $dto): string
    {
        $messageData = [
            'type' => $this->getDTOType($dto),
            // Assuming all DTOs have 'id' in their context for unique identification
            'id' => $dto->getContext()['id'] ?? null, 
            'context' => $dto->getContext(), // Include the full context
        ];

        // Add DTO-specific data if needed, or rely on context
        $this->addDTOSpecificData($messageData, $dto);

        try {
            return json_encode($messageData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            $this->logger->error('Failed to encode email DTO to JSON for Kafka message.', [
                'dto_class' => get_class($dto),
                'dto_context' => $dto->getContext(),
                'error' => $e->getMessage(),
            ]);
            throw $e; // Re-throw to indicate failure
        }
    }

    /**
     * Determines the message type string based on the provided DTO instance.
     *
     * @param AbstractEmailDTO $dto The email DTO.
     * @return string The type of the DTO (e.g., 'order', 'verification').
     * @throws \InvalidArgumentException If the DTO type is unknown.
     */
    private function getDTOType(AbstractEmailDTO $dto): string
    {
        return match(true) {
            $dto instanceof OrderDTO => 'order',
            $dto instanceof VerificationDTO => 'verification',
            default => throw new \InvalidArgumentException(sprintf('Unknown email DTO type: %s', get_class($dto))),
        };
    }

    /**
     * Adds DTO-specific data to the message payload.
     *
     * This method can be used to explicitly extract and add specific fields
     * from a DTO to the top-level of the Kafka message, rather than relying
     * solely on the `context` array. This might be useful for consumer convenience.
     *
     * @param array<string, mixed> &$messageData The message data array to modify by reference.
     * @param AbstractEmailDTO $dto The email DTO from which to extract specific data.
     */
    private function addDTOSpecificData(array &$messageData, AbstractEmailDTO $dto): void
    {
        // Example: If 'confirmUrl' or 'locale' are frequently accessed and not
        // deep within the context, you might want them at the top level.
        if ($dto instanceof VerificationDTO) {
            $messageData['confirmUrl'] = $dto->getConfirmUrl();
            $messageData['locale'] = $dto->getLocale();
        }
        // Add more DTO-specific data for other DTO types as needed
    }
}