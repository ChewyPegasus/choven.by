<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testEveryUserHasRoleUser(): void
    {
        self::assertSame([User::ROLE_USER], (new User())->getRoles());
    }

    public function testAdminFlag(): void
    {
        $user = (new User())->setAdmin(true);
        self::assertTrue($user->isAdmin());
        self::assertContains(User::ROLE_ADMIN, $user->getRoles());

        $user->setAdmin(false);
        self::assertFalse($user->isAdmin());
        self::assertSame([User::ROLE_USER], $user->getRoles());
    }

    public function testEmailIsNormalized(): void
    {
        self::assertSame('john@example.com', (new User())->setEmail('  John@Example.COM ')->getEmail());
    }

    public function testDisplayName(): void
    {
        $user = (new User())->setEmail('anna@example.com');
        self::assertSame('anna', $user->getDisplayName());
        self::assertSame('Анна', $user->setName(' Анна ')->getDisplayName());
    }
}
