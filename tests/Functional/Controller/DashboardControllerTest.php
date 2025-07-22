<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\BaseWebTestCase;
use App\Enum\Role;
use Symfony\Component\HttpFoundation\Response;

class DashboardControllerTest extends BaseWebTestCase
{
    private $adminUser;
    private $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = $this->createAdminUser();
        $this->regularUser = $this->createRegularUser();
    }

    public function testDashboardAccessDeniedForAnonymous(): void
    {
        $this->requestWithLocale('GET', '/admin');
        
        // Anonymous user should be redirected to login
        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('login', $location);
    }

    public function testDashboardAccessDeniedForRegularUser(): void
    {
        $this->client->loginUser($this->regularUser);
        
        $this->requestWithLocale('GET', '/admin');
        
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDashboardAccessForAdmin(): void
    {
        $this->client->loginUser($this->adminUser);
        
        $this->requestWithLocale('GET', '/admin');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.admin-dashboard');
    }

    public function testMakeAdminPage(): void
    {
        $this->client->loginUser($this->adminUser);
        
        $this->requestWithLocale('GET', '/admin/make-admin');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.admin-section');
    }

    public function testUsersPage(): void
    {
        $this->client->loginUser($this->adminUser);
        
        $this->requestWithLocale('GET', '/admin/users');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
    }

    public function testLogsPage(): void
    {
        $this->client->loginUser($this->adminUser);
        
        $this->requestWithLocale('GET', '/admin/logs');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#logContent');
    }

    /**
     * Test admin panel access with different locales
     */
    public function testAdminAccessWorksWithAllLocales(): void
    {
        $this->client->loginUser($this->adminUser);
        
        $locales = ['ru', 'be', 'en'];
        
        foreach ($locales as $locale) {
            $this->requestWithLocale('GET', '/admin', [], [], [], null, $locale);
            $this->assertResponseIsSuccessful("Admin access failed for locale: {$locale}");
        }
    }
}