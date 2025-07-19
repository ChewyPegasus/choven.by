<?php

declare(strict_types=1);

namespace App\Service\Messaging\Processor\Topic;

use App\Enum\EmailTemplate;
use App\Factory\EmailFactory;
use App\Repository\UserRepository;
use App\Service\Messaging\Processor\MessageProcessorInterface;
use App\Service\Sending\EmailSender;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Translation\LocaleSwitcher;

class VerificationMessageProcessor implements MessageProcessorInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EmailFactory $emailFactory,
        private readonly EmailSender $sender,
        private readonly LoggerInterface $logger,
        private readonly LocaleSwitcher $localeSwitcher,
    ) {
    }
    
    public function process(array $messageData, OutputInterface $output): bool
    {
        $userId = $messageData['id'] ?? null;
        $confirmUrl = $messageData['confirmUrl'] ?? null;
        
        if (!$userId) {
            $this->logger->warning('Verification message is missing user ID', ['data' => $messageData]);
            
            return false;
        }
        
        if (!$confirmUrl) {
            $this->logger->warning('Verification message is missing confirmUrl', ['data' => $messageData]);
            
            return false;
        }
        
        $user = $this->userRepository->find($userId);
        
        if (!$user) {
            $this->logger->error('User not found', ['userId' => $userId]);
            
            return false;
        }
        
        try {
            if (isset($messageData['locale'])) {
                $this->localeSwitcher->setLocale($messageData['locale']);
            }

            $emailDto = $this->emailFactory->createDTO(EmailTemplate::VERIFICATION, [
                'user' => $user,
                'confirmUrl' => $confirmUrl,
                'locale' => $messageData['locale'] ?? null,
            ]);
            
            $this->sender->send($emailDto);
            
            $output->writeln(sprintf('Verification email for user %d sent successfully.', $user->getId()));
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to process verification email', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            
            return false;
        }finally {
            // Reset locale
            $this->localeSwitcher->reset();
        }
    }
}