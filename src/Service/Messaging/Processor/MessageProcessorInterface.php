<?php

declare(strict_types=1);

namespace App\Service\Messaging\Processor;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface for a message processor.
 *
 * Defines the contract for services that process messages from a messaging system.
 * Implementations should provide a `process` method that handles the business logic
 * associated with a given message.
 */
interface MessageProcessorInterface
{
    /**
     * Processes a message.
     *
     * This method contains the core logic for handling a message received from
     * a messaging queue. It takes the message data and a console output interface
     * for logging or displaying progress.
     *
     * @param array<string, mixed> $messageData The decoded message data as an associative array.
     * @param OutputInterface $output The console output interface, used for writing messages to the console.
     * @return bool True if the message was processed successfully, false otherwise.
     */
    public function process(array $messageData, OutputInterface $output): bool;
}