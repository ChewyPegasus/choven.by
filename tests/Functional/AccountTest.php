<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\OrderStatus;
use App\Repository\UserRepository;
use App\Tests\Support\Factory;
use App\Tests\Support\WebTestCase;

final class AccountTest extends WebTestCase
{
    private function login(string $identifier, string $password = Factory::PASSWORD): void
    {
        $crawler = $this->client->request('GET', '/ru/login');
        $this->submit($crawler, 'Войти', ['identifier' => $identifier, 'password' => $password]);
    }

    public function testRegistrationEmailVerificationAndLogin(): void
    {
        $guestOrder = $this->factory->order('+7 days', email: 'new.user@example.com');

        $crawler = $this->client->request('GET', '/ru/register');
        $this->submit($crawler, 'Создать аккаунт', [
            'registration[name]' => 'Новый',
            'registration[email]' => 'New.User@example.com',
            'registration[phone]' => '+375 44 555-66-77',
            'registration[plainPassword][first]' => 'very-secret-1',
            'registration[plainPassword][second]' => 'very-secret-1',
            'registration[agreeTerms]' => '1',
        ]);
        self::assertResponseRedirects('/ru/register/check-email');
        self::assertEmailCount(1);
        $link = $this->lastEmailUrl('#https?://[^"\s<]+/verify\?[^"\s<]+#');

        // Not verified yet: login is refused with a hint.
        $this->login('new.user@example.com', 'very-secret-1');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-error', 'Подтвердите email');

        $this->client->request('GET', $link);
        self::assertResponseRedirects('/ru/login');

        $user = static::getContainer()->get(UserRepository::class)->findOneByEmail('new.user@example.com');
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isVerified());

        // The booking made as a guest is now in the account.
        $guestOrder = $this->reload($guestOrder);
        self::assertSame($user->getId(), $guestOrder->getUser()?->getId());

        $this->login('+375445556677', 'very-secret-1');
        self::assertResponseRedirects('/ru/profile/orders');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Новый');
        self::assertSelectorTextContains('#upcoming', $guestOrder->getReference());
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $this->factory->user('taken@example.com');

        $crawler = $this->client->request('GET', '/ru/register');
        $this->submit($crawler, 'Создать аккаунт', [
            'registration[email]' => 'TAKEN@example.com',
            'registration[plainPassword][first]' => 'very-secret-1',
            'registration[plainPassword][second]' => 'very-secret-1',
            'registration[agreeTerms]' => '1',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'уже зарегистрирован');
    }

    public function testWrongPassword(): void
    {
        $this->factory->user('user@example.com');

        $this->login('user@example.com', 'wrong-password');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-error', 'Неверный email/телефон или пароль');
    }

    public function testPasswordReset(): void
    {
        $this->factory->user('forgot@example.com');

        $crawler = $this->client->request('GET', '/ru/reset-password');
        $this->submit($crawler, 'Отправить ссылку', ['reset_password_request[email]' => 'forgot@example.com']);
        self::assertResponseRedirects('/ru/reset-password/check-email');
        self::assertEmailCount(1);

        $this->client->request('GET', $this->lastEmailUrl('#https?://[^"\s<]+/reset-password/reset/[^"\s<]+#'));
        self::assertResponseRedirects('/ru/reset-password/reset');
        $crawler = $this->client->followRedirect();
        $this->submit($crawler, 'Сохранить пароль', [
            'new_password[plainPassword][first]' => 'brand-new-pass',
            'new_password[plainPassword][second]' => 'brand-new-pass',
        ]);
        self::assertResponseRedirects('/ru/login');

        $this->login('forgot@example.com', 'brand-new-pass');
        self::assertResponseRedirects('/ru/profile/orders');
    }

    public function testUnknownEmailDoesNotRevealAnything(): void
    {
        $crawler = $this->client->request('GET', '/ru/reset-password');
        $this->submit($crawler, 'Отправить ссылку', ['reset_password_request[email]' => 'nobody@example.com']);

        self::assertResponseRedirects('/ru/reset-password/check-email');
        self::assertEmailCount(0);
    }

    public function testProfileCancelOrderAndSettings(): void
    {
        $user = $this->factory->user('me@example.com');
        $order = $this->factory->order('+8 days', user: $user);
        $foreign = $this->factory->order('+8 days', email: 'other@example.com');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/ru/profile/orders');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', $foreign->getReference());

        $this->submit($crawler, 'Отменить бронь');
        self::assertResponseRedirects('/ru/profile/orders');
        $order = $this->reload($order);
        self::assertSame(OrderStatus::CANCELLED, $order->getStatus());

        // Somebody else's booking cannot be cancelled through the profile.
        $this->client->request('POST', '/ru/profile/orders/'.$foreign->getReference().'/cancel', ['_token' => 'x']);
        self::assertResponseStatusCodeSame(403);

        $crawler = $this->client->request('GET', '/ru/profile/settings');
        $this->submit($crawler, 'Сохранить', [
            'profile[name]' => 'Мария',
            'profile[phone]' => '+375 29 111-11-11',
            'profile[locale]' => 'be',
        ]);
        self::assertResponseRedirects('/be/profile/settings');
        $user = $this->reload($user);
        self::assertSame('Мария', $user->getName());
        self::assertSame('be', $user->getLocale());

        $crawler = $this->client->request('GET', '/ru/profile/settings');
        $this->submit($crawler, 'Изменить пароль', [
            'new_password[currentPassword]' => Factory::PASSWORD,
            'new_password[plainPassword][first]' => 'another-pass-1',
            'new_password[plainPassword][second]' => 'another-pass-1',
        ]);
        self::assertResponseRedirects('/ru/profile/settings');
    }

    public function testAccountDeletionKeepsBookings(): void
    {
        $user = $this->factory->user('bye@example.com');
        $order = $this->factory->order('+8 days', user: $user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/ru/profile/settings');
        $this->submit($crawler, 'Удалить аккаунт');

        self::assertResponseRedirects('/ru/');
        $this->em->clear();
        self::assertNull(static::getContainer()->get(UserRepository::class)->findOneByEmail('bye@example.com'));
        self::assertNotNull($this->em->find(\App\Entity\Order::class, $order->getId()));
    }
}
