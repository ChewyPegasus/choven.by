<?php

declare(strict_types=1);

namespace App\Payment;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Picks the payment provider from PAYMENT_PROVIDER: "bepaid", "demo" (development only) or "none".
 */
class PaymentGateways
{
    private ?PaymentGatewayInterface $gateway = null;
    private bool $resolved = false;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%env(default::PAYMENT_PROVIDER)%')]
        private readonly ?string $provider = null,
        #[Autowire('%env(default::BEPAID_SHOP_ID)%')]
        private readonly ?string $bepaidShopId = null,
        #[Autowire('%env(default::BEPAID_SECRET_KEY)%')]
        private readonly ?string $bepaidSecretKey = null,
        #[Autowire('%env(default::BEPAID_TEST)%')]
        private readonly ?string $bepaidTest = null,
        #[Autowire('%env(default::BEPAID_ERIP_SERVICE_NO)%')]
        private readonly ?string $bepaidEripServiceNo = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return null !== $this->get();
    }

    /**
     * The configured gateway, or null when online payments are off.
     */
    public function get(): ?PaymentGatewayInterface
    {
        if ($this->resolved) {
            return $this->gateway;
        }
        $this->resolved = true;

        $this->gateway = match ($this->provider) {
            'bepaid' => $this->bepaid(),
            'demo' => 'prod' === $this->environment ? $this->refuseDemo() : new DemoGateway($this->urlGenerator),
            default => null,
        };

        return $this->gateway;
    }

    public function byName(string $name): ?PaymentGatewayInterface
    {
        $gateway = $this->get();

        return null !== $gateway && $gateway->getName() === $name ? $gateway : null;
    }

    private function bepaid(): ?PaymentGatewayInterface
    {
        if (null === $this->bepaidShopId || '' === $this->bepaidShopId || null === $this->bepaidSecretKey || '' === $this->bepaidSecretKey) {
            $this->logger->error('PAYMENT_PROVIDER=bepaid, but BEPAID_SHOP_ID / BEPAID_SECRET_KEY are not set: online payments are off.');

            return null;
        }

        return new BePaidGateway(
            $this->httpClient,
            $this->bepaidShopId,
            $this->bepaidSecretKey,
            filter_var($this->bepaidTest ?? 'false', \FILTER_VALIDATE_BOOL),
            null !== $this->bepaidEripServiceNo && ctype_digit($this->bepaidEripServiceNo) ? (int) $this->bepaidEripServiceNo : null,
        );
    }

    private function refuseDemo(): null
    {
        $this->logger->critical('PAYMENT_PROVIDER=demo is not allowed in production: online payments are off.');

        return null;
    }
}
