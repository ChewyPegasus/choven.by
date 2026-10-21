<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\User;
use App\Enum\Role;
use App\Tests\BaseWebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AdminApiTest extends BaseWebTestCase
{
    private $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = $this->createAdminUser();
        $this->client->loginUser($this->adminUser);
    }

    public function testMakeAdminPage(): void
    {
        $this->requestWithLocale('GET', '/admin/make-admin');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.admin-section');
    }

    public function testPromoteUserToAdmin(): void
    {
        $user = $this->createRegularUser('promote@test.com');

        $this->requestWithLocale('POST', "/api/admin/users/{$user->getId()}/promote");

        $this->assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);

        $this->entityManager->refresh($user);
        $this->assertTrue($user->isAdmin());
    }

    public function testRemoveAdminRights(): void
    {
        $secondAdmin = $this->createAdminUser('second@test.com');

        $this->requestWithLocale('POST', "/api/admin/users/{$secondAdmin->getId()}/demote");

        $this->assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);

        $this->entityManager->refresh($secondAdmin);
        $this->assertFalse($secondAdmin->isAdmin());
    }

    public function testCannotRemoveLastAdmin(): void
    {
        $adminCount = $this->entityManager->getRepository(User::class)->countByRole(Role::ADMIN);
        $this->assertEquals(1, $adminCount, "Test setup should have exactly one admin.");

        $this->requestWithLocale('POST', "/api/admin/users/{$this->adminUser->getId()}/demote");
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testCannotRemoveAdminRightsFromSelf(): void
    {
        $this->requestWithLocale('POST', "/api/admin/users/{$this->adminUser->getId()}/demote");
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('You cannot remove admin rights from yourself', $response['message']);
    }

    public function testUserSearch(): void
    {
        $this->createRegularUser('search-user1@test.com');
        $this->createRegularUser('search-user2@test.com');
        $this->createRegularUser('another-user@test.com');

        $this->requestWithLocale('GET', '/api/admin/users/search?q=search-user');

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('users', $response);
        $this->assertEquals(2, $response['count']);
    }

    public function testUserSearchAPI(): void
    {
        $user1 = $this->createRegularUser('search_test1@test.com');
        $user2 = $this->createRegularUser('search_test2@test.com');
        $user3 = $this->createRegularUser('different@test.com');

        $this->requestWithLocale('GET', '/admin/users/api/search?q=search_test');

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('users', $response);
        $this->assertEquals(2, $response['count']);
        $this->assertCount(2, $response['users']);

        $this->assertArrayHasKey('id', $response['users'][0]);
        $this->assertArrayHasKey('email', $response['users'][0]);
        $this->assertArrayHasKey('isConfirmed', $response['users'][0]);
        $this->assertArrayHasKey('phone', $response['users'][0]);
        $this->assertArrayHasKey('roles', $response['users'][0]);
    }

    public function testUserSearchShortQuery(): void
    {
        $this->requestWithLocale('GET', '/admin/users/api/search?q=a');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('at least 2 characters', $response['error']);
    }

    public function testToggleUserAdmin(): void
    {
        $user = $this->createRegularUser('toggle@test.com');

        $this->requestWithLocale('POST', "/admin/users/api/{$user->getId()}/toggle-admin");

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($response['success']);
        $this->assertTrue($response['user']['isAdmin']);

        $this->requestWithLocale('POST', "/admin/users/api/{$user->getId()}/toggle-admin");

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($response['success']);
        $this->assertFalse($response['user']['isAdmin']);
    }

    public function testBulkUserActions(): void
    {
        $user1 = $this->createRegularUser('bulk1@test.com');
        $user2 = $this->createRegularUser('bulk2@test.com');

        $this->client->request('POST', $this->getLocalizedUrl('/admin/users/api/bulk-action'), [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            json_encode([
                'action' => 'confirm',
                'userIds' => [$user1->getId(), $user2->getId()]
            ])
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($response['success']);
        $this->assertEquals(2, $response['processedCount']);

        $this->entityManager->refresh($user1);
        $this->entityManager->refresh($user2);
        
        $this->assertTrue($user1->isConfirmed());
        $this->assertTrue($user2->isConfirmed());
    }

    public function testToggleUserConfirmation(): void
    {
        $user = $this->createRegularUser('confirm@test.com');
        
        $this->assertFalse($user->isConfirmed());

        $this->requestWithLocale('POST', "/admin/users/api/{$user->getId()}/toggle-confirmation");

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($response['success']);
        $this->assertTrue($response['user']['isConfirmed']);

        $this->requestWithLocale('POST', "/admin/users/api/{$user->getId()}/toggle-confirmation");

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($response['success']);
        $this->assertFalse($response['user']['isConfirmed']);
    }

    public function testCreateUserAPI(): void
    {
        $userData = [
            'email' => 'api_created@test.com',
            'password' => 'password123',
            'phone' => '+375447891162',
            'isConfirmed' => true,
            'isAdmin' => false
        ];

        $this->client->request('POST', $this->getLocalizedUrl('/admin/users/api'), [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            json_encode($userData)
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('user', $response);

        $user = $this->entityManager->getRepository(\App\Entity\User::class)
            ->findOneBy(['email' => 'api_created@test.com']);
        
        $this->assertNotNull($user);
        $this->assertTrue($user->isConfirmed());
    }

    public function testGetUserById(): void
    {
        $user = $this->createRegularUser('get_by_id@test.com');

        $this->requestWithLocale('GET', "/admin/users/api/{$user->getId()}");

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertEquals($user->getId(), $response['id']);
        $this->assertEquals($user->getEmail(), $response['email']);
        $this->assertArrayHasKey('phone', $response);
        $this->assertArrayHasKey('isConfirmed', $response);
        $this->assertArrayHasKey('roles', $response);
    }

    public function testUpdateUserAPI(): void
    {
        $user = $this->createRegularUser('update@test.com');

        $updateData = [
            'email' => 'updated@test.com',
            'isConfirmed' => true,
            'isAdmin' => true
        ];

        $this->client->request('PUT', $this->getLocalizedUrl("/admin/users/api/{$user->getId()}"), [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            json_encode($updateData)
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($response['success']);

        $this->entityManager->refresh($user);
        $this->assertEquals('updated@test.com', $user->getEmail());
        $this->assertTrue($user->isConfirmed());
        $this->assertTrue($user->isAdmin());
    }

    public function testDeleteUserAPI(): void
    {
        $user = $this->createRegularUser('delete@test.com');
        $userId = $user->getId();

        $this->requestWithLocale('DELETE', "/admin/users/api/{$userId}");

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertTrue($response['success']);

        $deletedUser = $this->entityManager->getRepository(\App\Entity\User::class)->find($userId);
        $this->assertNull($deletedUser);
    }

    public function testCannotDeleteLastAdmin(): void
    {
        $this->requestWithLocale('DELETE', "/admin/users/api/{$this->adminUser->getId()}");

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('error', $response, "Response should contain 'error' key.");
        $this->assertStringContainsString('Cannot delete the last admin', $response['error'], "Error message should mention 'last admin'.");
    }

    public function testPromoteUserWorksWithRussianLocale(): void
    {
        $user = $this->createRegularUser('promote-ru@test.com');

        $this->requestWithLocale('POST', '/admin/make-admin', [
            'userId' => $user->getId()
        ], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], null, 'ru');

        $this->assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);

        $this->entityManager->refresh($user);
        $this->assertTrue($user->isAdmin());
    }
}
