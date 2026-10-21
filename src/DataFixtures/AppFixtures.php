<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ContactRequest;
use App\Entity\Order;
use App\Entity\Review;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\Package;
use App\Enum\ReviewStatus;
use App\Enum\River;
use App\Repository\TourRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Demo data for local development: `make fixtures` (appends to the tours and routes created by the migrations).
 *
 * Accounts: admin@choven.local / admin12345 and user@choven.local / user12345.
 */
final class AppFixtures extends Fixture
{
    private const NAMES = ['Алеся Карповіч', 'Иван Петров', 'Мария Сидоренко', 'Andrei Kowalski', 'Ольга Новик', 'Дмитрий Лис', 'Kate Smith', 'Павел Жук'];

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly TourRepository $tours,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        mt_srand(42);
        $phones = PhoneNumberUtil::getInstance();

        $admin = (new User())->setEmail('admin@choven.local')->setName('Администратор')->setIsVerified(true)->setAdmin(true);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin12345'));
        $manager->persist($admin);

        $user = (new User())->setEmail('user@choven.local')->setName('Алеся')->setIsVerified(true)
            ->setPhone($phones->parse('+375291112233', 'BY'));
        $user->setPassword($this->hasher->hashPassword($user, 'user12345'));
        $manager->persist($user);

        $tours = $this->tours->findActive();
        $completed = [];

        for ($i = 0; $i < 36; ++$i) {
            $offset = mt_rand(-120, 75);
            $tour = [] !== $tours && mt_rand(0, 3) > 0 ? $tours[array_rand($tours)] : null;
            $status = match (true) {
                $offset < -1 => mt_rand(0, 9) > 1 ? OrderStatus::COMPLETED : OrderStatus::CANCELLED,
                default => [OrderStatus::NEW, OrderStatus::CONFIRMED, OrderStatus::CONFIRMED, OrderStatus::CANCELLED][mt_rand(0, 3)],
            };
            $people = mt_rand(1, 12);
            $owner = 0 === $i % 6 ? $user : null;

            $order = (new Order())
                ->setName($owner?->getName() ?? self::NAMES[$i % \count(self::NAMES)])
                ->setEmail($owner?->getEmail() ?? \sprintf('guest%d@example.com', $i))
                ->setPhone($phones->parse(\sprintf('+37529%07d', 1000000 + $i * 7919), 'BY'))
                ->setRiver($tour?->getRiver() ?? [River::ISLOCH, River::SVISLOCH, River::BEREZINA][mt_rand(0, 2)])
                ->setPackage($tour?->getPackage() ?? Package::cases()[mt_rand(0, 3)])
                ->setTour($tour)
                ->setStartDate(new \DateTimeImmutable(\sprintf('%+d days', $offset)))
                ->setDurationDays($tour?->getDurationDays() ?? mt_rand(1, 3))
                ->setAmountOfPeople($people)
                ->setEstimatedPrice($tour?->estimatePrice($people))
                ->setDescription(0 === $i % 4 ? 'Будут дети, нужен трансфер из Минска' : null)
                ->setLocale(['ru', 'ru', 'be', 'en'][$i % 4])
                ->setUser($owner)
                ->setStatus($status);
            $manager->persist($order);

            if (OrderStatus::COMPLETED === $status) {
                $completed[] = $order;
            }
        }

        $texts = [
            'Отличная организация! Инструктор всё объяснил, байдарки удобные, маршрут невероятно красивый. Обязательно вернёмся всей семьёй.',
            'Ездили компанией на выходные — костёр, баня и тишина. Лучший отдых за лето!',
            'Вельмі прыгожы маршрут па Іслачы, усё прадумана да дробязяў. Дзякуй!',
            'Great trip for beginners, the guide was patient and friendly. Highly recommended.',
            'Брали только аренду — всё снаряжение чистое и исправное, привезли вовремя.',
        ];
        foreach (\array_slice($completed, 0, \count($texts)) as $index => $order) {
            $review = (new Review())
                ->setOrder($order)
                ->setAuthor($order->getUser())
                ->setAuthorName(explode(' ', $order->getName())[0])
                ->setRating($index % 3 ? 5 : 4)
                ->setText($texts[$index])
                ->setLocale($order->getLocale())
                ->setStatus(4 === $index ? ReviewStatus::PENDING : ReviewStatus::APPROVED);
            $manager->persist($review);
        }

        foreach (['Пётр' => 'Хотим корпоратив на 25 человек в июне', 'Анна' => null] as $name => $message) {
            $manager->persist((new ContactRequest())
                ->setName($name)
                ->setPhone($phones->parse('+375336667788', 'BY'))
                ->setMessage($message));
        }

        $manager->flush();
    }
}
