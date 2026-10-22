<?php

declare(strict_types=1);

namespace App\Command;

use App\Messenger\BotUpdateHandler;
use App\Messenger\TelegramBot;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Receives Telegram updates by long polling: for local development, where Telegram cannot reach a webhook.
 */
#[AsCommand(name: 'app:telegram:poll', description: 'Processes messages to the Telegram bot without a webhook (development)')]
final class TelegramPollCommand extends Command
{
    public function __construct(
        private readonly TelegramBot $bot,
        private readonly BotUpdateHandler $handler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Process pending updates once and exit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->bot->isEnabled()) {
            $io->error('Set TELEGRAM_BOT_TOKEN first.');

            return Command::FAILURE;
        }

        // Polling does not work while a webhook is set.
        $this->bot->deleteWebhook();
        $io->writeln('Waiting for messages to the bot… (Ctrl+C to stop)');

        $offset = 0;
        do {
            foreach ($this->bot->getUpdates($offset, $input->getOption('once') ? 0 : 25) as $update) {
                $offset = max($offset, (int) ($update['update_id'] ?? 0) + 1);
                $reply = $this->handler->handleTelegram($update);
                if (null !== $reply) {
                    $this->bot->sendMessage($reply['chat_id'], $reply['text']);
                    $io->writeln(\sprintf('→ %s: %s', $reply['chat_id'], strip_tags($reply['text'])));
                }
            }
        } while (!$input->getOption('once'));

        return Command::SUCCESS;
    }
}
