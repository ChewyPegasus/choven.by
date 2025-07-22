<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\User;
use App\Enum\Role;
use App\Repository\UserRepository;
use App\Tests\BaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserRepositoryTest extends BaseWebTestCase
{
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->entityManager->getRepository(User::class);
    }

    public function testFindByRole(): void
    {
        // Create test users
        $this->createAdminUser('admin-test1@test.com');
        $this->createAdminUser('admin-test2@test.com');
        $this->createRegularUser('user-test@test.com');

        // Test searching for admins
        $admins = $this->userRepository->findByRole(Role::ADMIN);
        $this->assertCount(2, $admins);
        foreach ($admins as $admin) {
            $this->assertTrue(in_array(Role::ADMIN->value, $admin->getRoles()));
        }
    }
}