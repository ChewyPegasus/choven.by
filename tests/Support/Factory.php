<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Order;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\Package;
use App\Enum\River;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Builds and persists test data.
 */
final class Factory
{
    public const PASSWORD = 'secret-password';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function user(string $email = 'user@example.com', bool $admin = false, bool $verified = true, ?string $phone = null): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setName('Test User')
            ->setIsVerified($verified)
            ->setAdmin($admin);
        if (null !== $phone) {
            $user->setPhone(PhoneNumberUtil::getInstance()->parse($phone, 'BY'));
        }
        $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function admin(string $email = 'admin@example.com'): User
    {
        return $this->user($email, admin: true);
    }

    public function order(
        string $startDate = '+10 days',
        int $people = 2,
        int $days = 1,
        OrderStatus $status = OrderStatus::NEW,
        ?User $user = null,
        string $email = 'guest@example.com',
    ): Order {
        $order = (new Order())
            ->setName('Ivan Petrov')
            ->setEmail($user?->getEmail() ?? $email)
            ->setPhone(PhoneNumberUtil::getInstance()->parse('+375291234567', 'BY'))
            ->setRiver(River::ISLOCH)
            ->setPackage(Package::ALL_INCLUSIVE)
            ->setStartDate(new \DateTimeImmutable($startDate))
            ->setDurationDays($days)
            ->setAmountOfPeople($people)
            ->setUser($user)
            ->setStatus($status);

        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }
}
