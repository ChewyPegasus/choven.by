<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment;

use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Payment\BePaidGateway;
use App\Payment\CheckoutRequest;
use App\Payment\PaymentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;

final class BePaidGatewayTest extends TestCase
{
    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    /**
     * @param list<array<string, mixed>> $responses
     */
    private function gateway(array $responses, ?int $eripServiceNo = null): BePaidGateway
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse((string) json_encode(array_shift($responses)), ['http_code' => 200]);
        });

        return new BePaidGateway($client, '361', 'secret', true, $eripServiceNo);
    }

    private function payment(int $amount = 90): Payment
    {
        $payment = new Payment('bepaid', $amount);
        (new \ReflectionProperty(Payment::class, 'id'))->setValue($payment, 17);

        return $payment;
    }

    public function testCheckoutIsCreatedWithAmountInKopecks(): void
    {
        $payment = $this->payment();
        $gateway = $this->gateway([['checkout' => ['token' => 'tok123', 'redirect_url' => 'https://checkout.bepaid.by/v2/checkout?token=tok123']]], eripServiceNo: 99999);

        $gateway->createCheckout($payment, new CheckoutRequest('Предоплата CH-ABC', 'a@example.com', 'be', 'https://site/return', 'https://site/notify'));

        self::assertSame('tok123', $payment->getExternalId());
        self::assertSame('https://checkout.bepaid.by/v2/checkout?token=tok123', $payment->getRedirectUrl());
        self::assertTrue($payment->isTest());

        self::assertSame('https://checkout.bepaid.by/ctp/api/checkouts', $this->requests[0]['url']);
        $body = json_decode((string) $this->requests[0]['options']['body'], true);
        self::assertSame(9000, $body['checkout']['order']['amount']);
        self::assertSame('BYN', $body['checkout']['order']['currency']);
        self::assertSame($payment->getPublicId(), $body['checkout']['order']['tracking_id']);
        self::assertSame('https://site/notify', $body['checkout']['settings']['notification_url']);
        self::assertSame('be', $body['checkout']['settings']['language']);
        self::assertSame(['credit_card', 'erip'], $body['checkout']['payment_method']['types']);
        self::assertSame(99999, $body['checkout']['payment_method']['erip']['service_no']);
    }

    public function testSuccessfulCheckoutIsVerified(): void
    {
        $payment = $this->payment()->setExternalId('tok123');
        $checkout = fn (int $amount, string $tracking): array => ['checkout' => [
            'status' => 'successful',
            'order' => ['amount' => $amount, 'currency' => 'BYN', 'tracking_id' => $tracking],
            'gateway_response' => ['payment' => ['uid' => 'tx-1', 'status' => 'successful']],
        ]];

        $result = $this->gateway([$checkout(9000, $payment->getPublicId())])->fetchStatus($payment);
        self::assertNotNull($result);
        self::assertSame(PaymentStatus::SUCCEEDED, $result->status);
        self::assertSame('tx-1', $result->transactionId);

        $this->expectException(PaymentException::class);
        $this->gateway([$checkout(100, $payment->getPublicId())])->fetchStatus($payment);
    }

    public function testOtherStatuses(): void
    {
        $payment = $this->payment()->setExternalId('tok');

        self::assertSame(PaymentStatus::FAILED, $this->gateway([['checkout' => ['status' => 'failed']]])->fetchStatus($payment)?->status);
        self::assertSame(PaymentStatus::CANCELLED, $this->gateway([['checkout' => ['status' => 'expired']]])->fetchStatus($payment)?->status);
        self::assertNull($this->gateway([['checkout' => ['status' => 'pending']]])->fetchStatus($payment));
    }

    public function testNotificationWithWrongCredentialsIsRejected(): void
    {
        $gateway = $this->gateway([]);
        $trackingId = str_repeat('a', 32);
        $body = (string) json_encode(['transaction' => ['tracking_id' => $trackingId, 'status' => 'successful']]);

        $valid = Request::create('/payment/notify/bepaid', 'POST', server: ['PHP_AUTH_USER' => '361', 'PHP_AUTH_PW' => 'secret'], content: $body);
        self::assertSame($trackingId, $gateway->parseNotification($valid));

        $forged = Request::create('/payment/notify/bepaid', 'POST', server: ['PHP_AUTH_USER' => '361', 'PHP_AUTH_PW' => 'guess'], content: $body);
        self::assertNull($gateway->parseNotification($forged));

        $garbage = Request::create('/payment/notify/bepaid', 'POST', content: '{"transaction":{"tracking_id":"../../etc"}}');
        self::assertNull($gateway->parseNotification($garbage));
    }
}
