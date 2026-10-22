<?php

declare(strict_types=1);

namespace App\Command;

use App\Messenger\TelegramBot;
use App\Messenger\ViberBot;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Registers the site as the webhook of the Telegram / Viber bot (needs a public HTTPS DEFAULT_URI).
 */
#[AsCommand(name: 'app:bot:webhook', description: 'Sets, removes or shows the webhook of the Telegram or Viber bot')]
final class BotWebhookCommand extends Command
{
    public function __construct(
        private readonly TelegramBot $telegram,
        private readonly ViberBot $viber,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('bot', InputArgument::REQUIRED, 'telegram or viber')
            ->addArgument('action', InputArgument::OPTIONAL, 'set, delete or info (Telegram only)', 'set');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $bot = (string) $input->getArgument('bot');
        $action = (string) $input->getArgument('action');

        if ('telegram' === $bot) {
            if (!$this->telegram->isEnabled()) {
                $io->error('Set TELEGRAM_BOT_TOKEN first.');

                return Command::FAILURE;
            }
            if ('set' === $action && (null === $this->telegram->getWebhookSecret() || '' === $this->telegram->getWebhookSecret())) {
                $io->error('Set TELEGRAM_WEBHOOK_SECRET (any random string, e.g. `openssl rand -hex 24`).');

                return Command::FAILURE;
            }

            $result = match ($action) {
                'set' => $this->telegram->setWebhook($this->url('app_webhook_telegram', $io)),
                'delete' => $this->telegram->deleteWebhook(),
                default => $this->telegram->getWebhookInfo(),
            };
        } elseif ('viber' === $bot) {
            if (!$this->viber->isEnabled()) {
                $io->error('Set VIBER_AUTH_TOKEN and VIBER_BOT_URI first.');

                return Command::FAILURE;
            }
            $result = $this->viber->setWebhook('delete' === $action ? '' : $this->url('app_webhook_viber', $io));
        } else {
            $io->error('The bot must be "telegram" or "viber".');

            return Command::INVALID;
        }

        $io->writeln((string) json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }

    private function url(string $route, SymfonyStyle $io): string
    {
        $url = $this->urlGenerator->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL);
        if (!str_starts_with($url, 'https://')) {
            $io->warning(\sprintf('The webhook URL %s is not HTTPS: bots accept only public HTTPS addresses. Check DEFAULT_URI.', $url));
        }
        $io->writeln('Webhook: '.$url);

        return $url;
    }
}
