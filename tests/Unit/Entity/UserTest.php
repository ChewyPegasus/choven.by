<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Enum\Role;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
    }

    public function testUserCreation(): void
    {
        $this->assertInstanceOf(User::class, $this->user);
        $this->assertTrue($this->user->hasRole(Role::USER));
        $this->assertFalse($this->user->isAdmin());
        $this->assertFalse($this->user->isConfirmed());
    }

    public function testSetAndGetEmail(): void
    {
        $email = 'test@example.com';
        $this->user->setEmail($email);
        
        $this->assertEquals($email, $this->user->getEmail());
        $this->assertEquals($email, $this->user->getUserIdentifier());
    }

    public function testRoleManagement(): void
    {
        // at first only USER
        $this->assertTrue($this->user->hasRole(Role::USER));
        $this->assertFalse($this->user->isAdmin());

        // adding ADMIN
        $this->user->addRole(Role::ADMIN);
        $this->assertTrue($this->user->hasRole(Role::ADMIN));
        $this->assertTrue($this->user->isAdmin());

        // removing ADMIN
        $this->user->removeRole(Role::ADMIN);
        $this->assertFalse($this->user->hasRole(Role::ADMIN));
        $this->assertFalse($this->user->isAdmin());
    }

    public function testSetRoles(): void
    {
        $roles = [Role::USER, Role::ADMIN];
        $this->user->setRoles($roles);

        $this->assertTrue($this->user->hasRole(Role::USER));
        $this->assertTrue($this->user->hasRole(Role::ADMIN));
        $this->assertTrue($this->user->isAdmin());
    }

    public function testGetRoles(): void
    {
        $this->user->addRole(Role::ADMIN);
        $roles = $this->user->getRoles();

        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
    }

    public function testPhoneNumber(): void
    {
        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        $phoneNumber = $phoneUtil->parse('+375447891161', 'BY');
        
        $this->user->setPhone($phoneNumber);
        $this->assertEquals($phoneNumber, $this->user->getPhone());
        $this->assertEquals('+375 44 789-11-61', $this->user->getPhoneString());
    }

    public function testConfirmation(): void
    {
        $confirmationCode = 'test_code_123';
        
        $this->user->setConfirmationCode($confirmationCode);
        $this->user->setIsConfirmed(false);

        $this->assertEquals($confirmationCode, $this->user->getConfirmationCode());
        $this->assertFalse($this->user->isConfirmed());

        // confirming user
        $this->user->setIsConfirmed(true);
        $this->user->setConfirmationCode(null);

        $this->assertTrue($this->user->isConfirmed());
        $this->assertNull($this->user->getConfirmationCode());
    }

    public function testNoDuplicateRoles(): void
    {
        $this->user->addRole(Role::ADMIN);
        $this->user->addRole(Role::ADMIN); // adding twice

        $roleEnums = $this->user->getRoleEnums();
        $adminRoles = array_filter($roleEnums, fn($role) => $role === Role::ADMIN);
        
        $this->assertCount(1, $adminRoles, 'Should not have duplicate ADMIN roles');
    }
}