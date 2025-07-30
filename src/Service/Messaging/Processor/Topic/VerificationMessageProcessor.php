<?php

declare(strict_types=1);

namespace App\Service\Messaging\Processor\Topic;

use App\Enum\EmailTemplate;
use App\Factory\EmailFactory;
use App\Repository\Interfaces\UserRepositoryInterface;
use App\Service\Messaging\Processor\MessageProcessorInterface;
use App\Service\Sending\EmailSender;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Processes messages related to user email verification from a messaging queue.
 *
 * This processor is responsible for retrieving user details, creating a verification
 * email DTO, and sending the email. It also handles locale switching for emails.
 */
class VerificationMessageProcessor implements MessageProcessorInterface
{
    /**
     * Constructs a new VerificationMessageProcessor instance.
     *
     * @param UserRepositoryInterface $userRepository The repository for managing User entities.
     * @param EmailFactory $emailFactory The factory for creating email-related DTOs.
     * @param EmailSender $sender The service for sending emails.
     * @param LoggerInterface $logger The logger instance for recording activities and errors.
     * @param LocaleSwitcher $localeSwitcher The service for managing the application locale.
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly EmailFactory $emailFactory,
        private readonly EmailSender $sender,
        private readonly LoggerInterface $logger,
        private readonly LocaleSwitcher $localeSwitcher,
    ) {
    }
    
    /**
     * Processes a verification message.
     *
     * This method extracts the user ID and confirmation URL from the message data,
     * retrieves the user from the repository, sets the appropriate locale,
     * creates a verification email DTO, and attempts to send the email.
     * It logs success or failure and ensures the locale is reset afterwards.
     *
     * @param array<string, mixed> $messageData The decoded message data, expected to contain 'id', 'confirmUrl', and optionally 'locale'.
     * @param OutputInterface $output The console output interface for displaying messages.
     * @return bool True if the message was processed successfully, false otherwise.
     */
    public function process(array $messageData, OutputInterface $output): bool
    {
        // Reset locale at the beginning to ensure a clean state for each message
        $this->localeSwitcher->reset();

        $userId = $messageData['id'] ?? null;
        $confirmUrl = $messageData['confirmUrl'] ?? null;
        
        if (!$userId) {
            $this->logger->warning('Verification message is missing user ID', ['data' => $messageData]);
            $output->writeln('<error>Verification message is missing user ID.</error>');
            return false;
        }
        
        if (!$confirmUrl) {
            $this->logger->warning('Verification message is missing confirmUrl', ['data' => $messageData]);
            $output->writeln('<error>Verification message is missing confirmUrl.</error>');
            return false;
        }
        
        $user = $this->userRepository->find($userId);
        
        if (!$user) {
            $this->logger->error('User not found for verification email', ['userId' => $userId]);
            $output->writeln(sprintf('<error>User with ID %s not found for verification email.</error>', $userId));
            return false;
        }
        
        try {
            // Set locale if it is present in the message data
            if (isset($messageData['locale'])) {
                $this->localeSwitcher->setLocale($messageData['locale']);
                $this->logger->debug(sprintf('Locale set to "%s" for user %d verification.', $messageData['locale'], $user->getId()));
            }

            // Create the VerificationDTO for email sending
            $emailDto = $this->emailFactory->createDTO(EmailTemplate::VERIFICATION, [
                'user' => $user,
                'confirmUrl' => $confirmUrl,
                'locale' => $messageData['locale'] ?? null, // Pass locale to DTO
            ]);
            
            // Send the email
            $this->sender->send($emailDto);
            
            $output->writeln(sprintf('Verification email for user %d sent successfully in locale "%s".', $user->getId(), $this->localeSwitcher->getLocale()));
            $this->logger->info(sprintf('Verification email sent for user ID: %d', $user->getId()));
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to process verification email', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            $output->writeln(sprintf('<error>Failed to send verification email for user %d: %s</error>', $user->getId(), $e->getMessage()));
            
            return false;
        } finally {
            // Always reset locale after processing to avoid affecting subsequent operations
            $this->localeSwitcher->reset();
            $this->logger->debug('Locale reset to default.');
        }
    }
}