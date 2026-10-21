<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Security\UserProvider;
use App\Tests\Support\Factory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

final class UserProviderTest extends KernelTestCase
{
    public function testLoadsUserByEmailOrPhoneInAnyFormat(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $factory = new Factory($container->get(EntityManagerInterface::class), $container->get(UserPasswordHasherInterface::class));
        $user = $factory->user('anna@example.com', phone: '+375 29 706-75-85');
        $provider = $container->get(UserProvider::class);

        self::assertSame($user->getId(), $provider->loadUserByIdentifier(' Anna@Example.com ')->getId());
        foreach (['+375297067585', '8 029 706 75 85', '80297067585', '+375 (29) 706-75-85'] as $phone) {
            self::assertSame($user->getId(), $provider->loadUserByIdentifier($phone)->getId(), $phone);
        }

        $this->expectException(UserNotFoundException::class);
        $provider->loadUserByIdentifier('+375 29 000-00-00');
    }
}
