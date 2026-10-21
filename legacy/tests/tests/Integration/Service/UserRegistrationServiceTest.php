<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\User;
use App\Enum\Role;
use App\Service\Registration\UserRegistrationService;
use App\Tests\BaseWebTestCase;

class UserRegistrationServiceTest extends BaseWebTestCase
{
    private UserRegistrationService $registrationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->registrationService = static::getContainer()
            ->get(UserRegistrationService::class);
    }

    public function testRegisterUser(): void
    {
        $user = new User();
        $user->setEmail('test@registration.com');
        
        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        $phoneNumber = $phoneUtil->parse('+375447891161', 'BY');
        $user->setPhone($phoneNumber);

        $this->registrationService->registerUser($user, 'password123');

        $savedUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'test@registration.com']);

        $this->assertNotNull($savedUser);
        $this->assertFalse($savedUser->isConfirmed());
        $this->assertNotNull($savedUser->getConfirmationCode());
        $this->assertTrue($savedUser->hasRole(Role::USER));
        $this->assertNotEmpty($savedUser->getPassword());
    }

    public function testUserPasswordIsHashed(): void
    {
        $user = new User();
        $user->setEmail('hash@test.com');
        
        $plainPassword = 'myPlainPassword123';
        $this->registrationService->registerUser($user, $plainPassword);

        $savedUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'hash@test.com']);

        $this->assertNotEquals($plainPassword, $savedUser->getPassword());
        $this->assertStringStartsWith('$2y$', $savedUser->getPassword());
    }

    // public function testRegisterUserWithDuplicateEmail(): void
    // {
    //     $existingUser = $this->createTestUser('duplicate@test.com');

    //     $newUser = new User();
    //     $newUser->setEmail('duplicate@test.com');
        
    //     $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
    //     $phoneNumber = $phoneUtil->parse('+375447891162', 'BY');
    //     $newUser->setPhone($phoneNumber);

    //     $this->expectException(\Exception::class);
    //     $this->registrationService->registerUser($newUser, 'password123');
    // }

    public function testRegisterUserGeneratesConfirmationCode(): void
    {
        $user = new User();
        $user->setEmail('confirmation@test.com');
        
        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        $phoneNumber = $phoneUtil->parse('+375447891163', 'BY');
        $user->setPhone($phoneNumber);

        $this->registrationService->registerUser($user, 'password123');

        $savedUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'confirmation@test.com']);

        $this->assertNotNull($savedUser->getConfirmationCode());
        $this->assertNotEmpty($savedUser->getConfirmationCode());
        $this->assertIsString($savedUser->getConfirmationCode());
        $this->assertGreaterThan(10, strlen($savedUser->getConfirmationCode()));
    }

    public function testRegisterUserSetsDefaultRole(): void
    {
        $user = new User();
        $user->setEmail('role@test.com');
        
        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        $phoneNumber = $phoneUtil->parse('+375447891164', 'BY');
        $user->setPhone($phoneNumber);

        $this->registrationService->registerUser($user, 'password123');

        $savedUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'role@test.com']);

        $this->assertTrue($savedUser->hasRole(Role::USER));
        $this->assertFalse($savedUser->isAdmin());
        $this->assertContains('ROLE_USER', $savedUser->getRoles());
    }
}