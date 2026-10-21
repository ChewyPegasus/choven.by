<?php

declare(strict_types=1);

namespace App\Service\Messaging\Processor;

/**
 * Registry for MessageProcessorInterface implementations.
 *
 * This class acts as a central registry to manage and retrieve different
 * message processors based on their type. It allows for dynamic selection
 * of the correct processor for a given message.
 */
class MessageProcessorRegistry
{
    /**
     * @var array<string, MessageProcessorInterface> An associative array where keys are message types (strings)
     * and values are instances of `MessageProcessorInterface`.
     */
    private array $processors = [];
    
    /**
     * Registers a message processor with a specific type.
     *
     * This method adds a `MessageProcessorInterface` implementation to the registry,
     * associating it with a unique string identifier (type).
     *
     * @param string $type The unique type identifier for the processor (e.g., 'order_email', 'user_verification').
     * @param MessageProcessorInterface $processor The processor instance to register.
     */
    public function registerProcessor(string $type, MessageProcessorInterface $processor): void
    {
        $this->processors[$type] = $processor;
    }
    
    /**
     * Checks if a processor for a given type is registered.
     *
     * @param string $type The type identifier to check.
     * @return bool True if a processor for the given type exists, false otherwise.
     */
    public function hasProcessor(string $type): bool
    {
        return isset($this->processors[$type]);
    }
    
    /**
     * Retrieves a registered message processor by its type.
     *
     * @param string $type The type identifier of the processor to retrieve.
     * @return MessageProcessorInterface The registered message processor.
     * @throws \InvalidArgumentException If no processor is found for the given type.
     */
    public function getProcessor(string $type): MessageProcessorInterface
    {
        if (!$this->hasProcessor($type)) {
            throw new \InvalidArgumentException(sprintf('Processor for type "%s" not found', $type));
        }
        
        return $this->processors[$type];
    }
}