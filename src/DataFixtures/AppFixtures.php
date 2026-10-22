<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ContactRequest;
use App\Entity\Extra;
use App\Entity\GiftCertificate;
use App\Entity\Order;
use App\Entity\Post;
use App\Entity\PromoCode;
use App\Entity\Review;
use App\Entity\User;
use App\Enum\ExtraUnit;
use App\Enum\OrderStatus;
use App\Enum\Package;
use App\Enum\PromoType;
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
                ->setBasePrice($tour?->estimatePrice($people))
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

        $this->loadSales($manager);
        $this->loadPosts($manager, $tours);

        $manager->flush();
    }

    /**
     * Example add-ons, a promo code and a gift certificate (prices are examples: set the real ones in the admin panel).
     */
    private function loadSales(ObjectManager $manager): void
    {
        $extras = [
            [['ru' => 'Трансфер из Минска', 'be' => 'Трансфер з Мінска', 'en' => 'Transfer from Minsk'], ['ru' => 'Микроавтобус от ст. м. «Уручье» и обратно', 'be' => 'Мікрааўтобус ад ст. м. «Уручча» і назад', 'en' => 'Minibus from Uruchye metro station and back'], 25, ExtraUnit::PER_PERSON, false, 'fa-van-shuttle'],
            [['ru' => 'Аренда палатки', 'be' => 'Арэнда палаткі', 'en' => 'Tent rental'], ['ru' => 'Трёхместная палатка', 'be' => 'Трохмесная палатка', 'en' => 'Three-person tent'], 15, ExtraUnit::PER_ITEM, true, 'fa-campground'],
            [['ru' => 'Спальник и коврик', 'be' => 'Спальнік і дыванок', 'en' => 'Sleeping bag and mat'], [], 8, ExtraUnit::PER_ITEM, true, 'fa-bed'],
            [['ru' => 'Гермомешок 60 л', 'be' => 'Герметычны мех 60 л', 'en' => 'Dry bag 60 l'], [], 5, ExtraUnit::PER_ITEM, false, 'fa-bag-shopping'],
            [['ru' => 'Фотограф на маршруте', 'be' => 'Фатограф на маршруце', 'en' => 'Photographer on the route'], ['ru' => '100+ обработанных фото через 3 дня', 'be' => '100+ апрацаваных фота праз 3 дні', 'en' => '100+ edited photos in 3 days'], 150, ExtraUnit::PER_ORDER, false, 'fa-camera'],
        ];
        foreach ($extras as $position => [$name, $description, $price, $unit, $perDay, $icon]) {
            $manager->persist((new Extra())
                ->setName($name)
                ->setDescription($description)
                ->setPrice($price)
                ->setUnit($unit)
                ->setPerDay($perDay)
                ->setIcon($icon)
                ->setPosition($position + 1));
        }

        $manager->persist((new PromoCode())
            ->setCode('SUMMER10')
            ->setType(PromoType::PERCENT)
            ->setValue(10)
            ->setValidUntil(new \DateTimeImmutable('+6 months'))
            ->setNote('Демо: 10% на лето'));

        $gift = (new GiftCertificate())
            ->setAmount(200)
            ->setBuyerName('Ольга Новик')
            ->setBuyerEmail('olga@example.com')
            ->setRecipientName('Сергей')
            ->setMessage('С днём рождения!')
            ->activate();
        $manager->persist($gift);
    }

    /**
     * @param list<\App\Entity\Tour> $tours
     */
    private function loadPosts(ObjectManager $manager, array $tours): void
    {
        $posts = [
            [
                'first-kayak-trip',
                ['ru' => 'Первый сплав: как подготовиться и ничего не забыть', 'be' => 'Першы сплаў: як падрыхтавацца і нічога не забыць', 'en' => 'Your first kayak trip: how to prepare'],
                ['ru' => 'Что надеть, что взять с собой и чего ждать от первого дня на воде.', 'be' => 'Што апрануць, што ўзяць з сабой і чаго чакаць ад першага дня на вадзе.', 'en' => 'What to wear, what to bring and what to expect from your first day on the water.'],
                ['ru' => "Первый сплав — это всегда немного волнительно. Хорошая новость: опыт не нужен, всему научим на месте.\n\n## Что надеть\n\n- быстросохнущую одежду (не хлопок);\n- обувь, которую не жалко намочить;\n- головной убор и солнцезащитные очки.\n\n## Что взять с собой\n\n- сменную одежду в гермомешке;\n- воду и перекус;\n- крем от солнца и средство от комаров.\n\n> Главное правило — спасательный жилет надет всегда.\n\nОстальное — снаряжение, инструктаж и хорошее настроение — с нас.", 'en' => "A first trip is always a little exciting. The good news: no experience needed, we teach everything on site.\n\n## What to wear\n\n- quick-drying clothes (not cotton);\n- shoes you do not mind getting wet;\n- a hat and sunglasses.\n\n> The main rule: the life jacket is always on."],
            ],
            [
                'isloch-vs-svisloch',
                ['ru' => 'Ислочь или Свислочь: какую реку выбрать', 'be' => 'Іслач або Свіслач: якую раку выбраць', 'en' => 'Isloch or Svisloch: which river to choose'],
                ['ru' => 'Сравниваем два самых популярных маршрута рядом с Минском.', 'be' => 'Параўноўваем два самыя папулярныя маршруты побач з Мінскам.', 'en' => 'Comparing the two most popular routes near Minsk.'],
                ['ru' => "**Ислочь** — быстрая лесная река с поворотами и перекатами: для тех, кто хочет немного драйва.\n\n**Свислочь** — спокойная и широкая, идеальна для семей с детьми и первого знакомства с байдаркой.\n\n| | Ислочь | Свислочь |\n|---|---|---|\n| Течение | быстрое | спокойное |\n| Для детей | с 10 лет | с 5 лет |\n| Ночёвка | да | по запросу |"],
            ],
        ];

        foreach ($posts as $index => [$slug, $title, $excerpt, $body]) {
            $manager->persist((new Post())
                ->setSlug($slug)
                ->setTitle($title)
                ->setExcerpt($excerpt)
                ->setBody($body)
                ->setAuthor('Команда Чöвен')
                ->setTour($tours[$index] ?? null)
                ->setIsPublished(true)
                ->setPublishedAt(new \DateTimeImmutable(\sprintf('-%d days', 10 + $index * 12))));
        }
    }
}
