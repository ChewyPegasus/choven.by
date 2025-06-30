<?php

declare(strict_types=1);

namespace App\Service\Messaging\Processor;

use Symfony\Component\Console\Output\OutputInterface;

interface MessageProcessorInterface
{
    /**
     * Processes a message
     * 
     * @param array $messageData Message data
     * @param OutputInterface $output Console output
     * @return bool Processing result
     */
    public function process(array $messageData, OutputInterface $output): bool;
}