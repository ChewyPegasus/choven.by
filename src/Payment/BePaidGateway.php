<?php

declare(strict_types=1);

namespace App\Payment;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * bePaid (https://bepaid.by): bank cards, Apple Pay / Google Pay and ERIP through the hosted payment page.
 *
 * The status is never taken from the notification body: after every notification or return to the site
 * the checkout is queried from the API with our credentials.
 */
final class BePaidGateway implements PaymentGatewayInterface
{
    private const CHECKOUT_URL = 'https://checkout.bepaid.by';
    private const GATEWAY_URL = 'https://gateway.bepaid.by';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $shopId,
        private readonly string $secretKey,
        private readonly bool $test,
        private readonly ?int $eripServiceNo = null,
    ) {
    }

    public function getName(): string
    {
        return 'bepaid';
    }

    public function isTest(): bool
    {
        return $this->test;
    }

    public function createCheckout(Payment $payment, CheckoutRequest $request): void
    {
        $paymentMethods = ['types' => ['credit_card']];
        if (null !== $this->eripServiceNo && null !== $payment->getId()) {
            $paymentMethods['types'][] = 'erip';
            $paymentMethods['erip'] = [
                'order_id' => $payment->getId(),
                'account_number' => (string) $payment->getId(),
                'service_no' => $this->eripServiceNo,
                'service_info' => [mb_substr($request->description, 0, 100)],
            ];
        }

        $data = $this->request('POST', self::CHECKOUT_URL.'/ctp/api/checkouts', [
            'checkout' => [
                'test' => $this->test,
                'transaction_type' => 'payment',
                'attempts' => 3,
                'settings' => [
                    'success_url' => $request->returnUrl,
                    'decline_url' => $request->returnUrl,
                    'fail_url' => $request->returnUrl,
                    'cancel_url' => $request->returnUrl,
                    'notification_url' => $request->notifyUrl,
                    'language' => \in_array($request->locale, ['ru', 'be', 'en'], true) ? $request->locale : 'ru',
                    'customer_fields' => ['visible' => ['email'], 'read_only' => ['email']],
                ],
                'order' => [
                    'currency' => $payment->getCurrency(),
                    'amount' => $payment->getAmount() * 100,
                    'description' => mb_substr($request->description, 0, 255),
                    'tracking_id' => $payment->getPublicId(),
                ],
                'customer' => ['email' => $request->customerEmail],
                'payment_method' => $paymentMethods,
            ],
        ]);

        $token = $data['checkout']['token'] ?? null;
        $url = $data['checkout']['redirect_url'] ?? null;
        if (!\is_string($token) || !\is_string($url) || !str_starts_with($url, 'https://')) {
            throw new PaymentException('bePaid did not return a checkout token.');
        }

        $payment->setExternalId($token)->setRedirectUrl($url)->setTest($this->test);
    }

    public function fetchStatus(Payment $payment): ?PaymentResult
    {
        $token = $payment->getExternalId();
        if (null === $token) {
            return null;
        }

        $data = $this->request('GET', self::CHECKOUT_URL.'/ctp/api/checkouts/'.rawurlencode($token));
        $checkout = \is_array($data['checkout'] ?? null) ? $data['checkout'] : [];
        $transaction = \is_array($checkout['gateway_response']['payment'] ?? null) ? $checkout['gateway_response']['payment'] : [];

        $status = $checkout['status'] ?? $transaction['status'] ?? null;
        $uid = isset($transaction['uid']) && \is_string($transaction['uid']) ? $transaction['uid'] : null;
        $message = isset($transaction['message']) && \is_string($transaction['message']) ? $transaction['message'] : null;

        if ('successful' === $status) {
            // Make sure the checkout really belongs to this payment and covers the full amount.
            $order = \is_array($checkout['order'] ?? null) ? $checkout['order'] : [];
            if (($order['tracking_id'] ?? null) !== $payment->getPublicId()
                || (int) ($order['amount'] ?? 0) !== $payment->getAmount() * 100
                || ($order['currency'] ?? null) !== $payment->getCurrency()) {
                throw new PaymentException(\sprintf('bePaid checkout %s does not match payment %s.', $token, $payment->getPublicId()));
            }

            return new PaymentResult(PaymentStatus::SUCCEEDED, $uid, $message);
        }

        return match ($status) {
            'failed', 'error' => new PaymentResult(PaymentStatus::FAILED, $uid, $message),
            'expired' => new PaymentResult(PaymentStatus::CANCELLED, $uid, $message),
            default => null,
        };
    }

    public function parseNotification(Request $request): ?string
    {
        // bePaid signs notifications with HTTP Basic auth using the shop credentials.
        $user = $request->getUser();
        if (null !== $user && (!hash_equals($this->shopId, $user) || !hash_equals($this->secretKey, (string) $request->getPassword()))) {
            return null;
        }

        try {
            $data = json_decode($request->getContent(), true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $trackingId = \is_array($data) ? ($data['transaction']['tracking_id'] ?? $data['order']['tracking_id'] ?? null) : null;

        return \is_string($trackingId) && 1 === preg_match('/^[a-f0-9]{32}$/', $trackingId) ? $trackingId : null;
    }

    public function refund(Payment $payment, int $amount, string $reason): void
    {
        if (null === $payment->getTransactionId()) {
            throw new PaymentException('The payment has no transaction to refund.');
        }

        $data = $this->request('POST', self::GATEWAY_URL.'/transactions/refunds', [
            'request' => [
                'parent_uid' => $payment->getTransactionId(),
                'amount' => $amount * 100,
                'reason' => mb_substr($reason, 0, 255),
            ],
        ]);

        if (($data['transaction']['status'] ?? null) !== 'successful') {
            $message = $data['transaction']['message'] ?? $data['message'] ?? 'unknown error';
            throw new PaymentException('Refund failed: '.(\is_string($message) ? $message : 'unknown error'));
        }
    }

    /**
     * @param array<string, mixed>|null $json
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, ?array $json = null): array
    {
        $options = [
            'auth_basic' => [$this->shopId, $this->secretKey],
            'headers' => ['Accept' => 'application/json', 'X-API-Version' => '2'],
            'timeout' => 15,
        ];
        if (null !== $json) {
            $options['json'] = $json;
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $data = $response->toArray(false);
            if ($response->getStatusCode() >= 400) {
                $message = $data['message'] ?? $data['errors'] ?? $response->getStatusCode();
                throw new PaymentException('bePaid error: '.json_encode($message, \JSON_UNESCAPED_UNICODE));
            }

            return $data;
        } catch (ExceptionInterface $e) {
            throw new PaymentException('bePaid request failed: '.$e->getMessage(), 0, $e);
        }
    }
}
