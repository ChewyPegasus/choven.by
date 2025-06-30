<?php

declare(strict_types=1);

namespace App\Service\Messaging\Processor;

class MessageProcessorRegistry
{
    /**
     * @var array<string, MessageProcessorInterface>
     */
    private array $processors = [];
    
    public function registerProcessor(string $type, MessageProcessorInterface $processor): void
    {
        $this->processors[$type] = $processor;
    }
    
    public function hasProcessor(string $type): bool
    {
        return isset($this->processors[$type]);
    }
    
    public function getProcessor(string $type): MessageProcessorInterface
    {
        if (!$this->hasProcessor($type)) {
            throw new \InvalidArgumentException(sprintf('Processor for type "%s" not found', $type));
        }
        
        return $this->processors[$type];
    }
}