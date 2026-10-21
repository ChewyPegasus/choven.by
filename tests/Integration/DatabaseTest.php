<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Enum\OrderStatus;
use App\Message\CompletePastOrders;
use App\MessageHandler\CompletePastOrdersHandler;
use App\Model\OrderFilter;
use App\Repository\OrderRepository;
use App\Repository\RaftingRouteRepository;
use App\Repository\TourRepository;
use App\Tests\Support\Factory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DatabaseTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Factory $factory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->factory = new Factory($this->em, self::getContainer()->get(UserPasswordHasherInterface::class));
    }

    public function testMigrationsMatchTheMapping(): void
    {
        $validator = new SchemaValidator($this->em);

        self::assertSame([], $validator->validateMapping());
        $diff = array_values(array_filter(
            $validator->getUpdateSchemaList(),
            static fn (string $sql): bool => !str_contains($sql, 'doctrine_migration_versions'),
        ));
        self::assertSame([], $diff, 'Run "make migration": the database schema differs from the entities.');
    }

    public function testMigrationsSeedToursAndRoutes(): void
    {
        $tours = self::getContainer()->get(TourRepository::class)->findActive();
        $routes = self::getContainer()->get(RaftingRouteRepository::class)->findActive();

        self::assertCount(5, $tours);
        self::assertCount(2, $routes);
        self::assertSame('Сплав по Ислочи', $tours[1]->getLocalizedTitle('ru'));
        self::assertSame('isloch', $tours[1]->getRoute()?->getSlug());
        self::assertGreaterThanOrEqual(2, \count($routes[0]->getPoints()));
    }

    public function testOrderSearchByReferenceNamePhoneAndEmail(): void
    {
        $order = $this->factory->order(email: 'search.me@example.com');
        $repository = self::getContainer()->get(OrderRepository::class);

        foreach ([$order->getReference(), 'ivan petr', '1234567', 'search.me'] as $query) {
            $filter = new OrderFilter();
            $filter->query = $query;
            self::assertSame([$order->getId()], array_map(static fn ($o) => $o->getId(), $repository->findByFilter($filter, 1)->items), $query);
        }
    }

    public function testCompletingPastTrips(): void
    {
        $past = $this->factory->order('-3 days', status: OrderStatus::CONFIRMED);
        $stillOnTheWater = $this->factory->order('-1 day', days: 3, status: OrderStatus::CONFIRMED);
        $unconfirmed = $this->factory->order('-3 days');

        $handler = self::getContainer()->get(CompletePastOrdersHandler::class);
        self::assertSame(1, $handler(new CompletePastOrders()));

        $this->em->refresh($past);
        $this->em->refresh($stillOnTheWater);
        $this->em->refresh($unconfirmed);
        self::assertSame(OrderStatus::COMPLETED, $past->getStatus());
        self::assertSame(OrderStatus::CONFIRMED, $stillOnTheWater->getStatus());
        self::assertSame(OrderStatus::NEW, $unconfirmed->getStatus());
        self::assertEmailCount(1);
    }
}
