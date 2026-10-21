<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\BaseWebTestCase;

class LocalizationTest extends BaseWebTestCase
{
    /**
     * Check that main pages work with all locales
     */
    public function testMainPagesWorkWithAllLocales(): void
    {
        $pages = [
            '/',
            '/info',
            '/order/new',
            '/login',
            '/register'
        ];

        $locales = $this->getAvailableLocales();

        foreach ($locales as $locale) {
            foreach ($pages as $page) {
                $this->requestWithLocale('GET', $page, [], [], [], null, $locale);
                
                $this->assertResponseIsSuccessful(
                    "Page {$page} failed for locale {$locale}. Response: " . 
                    $this->client->getResponse()->getContent()
                );
            }
        }
    }

    /**
     * Check that admin pages work with all locales
     */
    public function testAdminPagesWorkWithAllLocales(): void
    {
        $adminUser = $this->createAdminUser();
        $this->client->loginUser($adminUser);

        $adminPages = [
            '/admin',
            '/admin/users',
            '/admin/make-admin',
            '/admin/logs',
            '/admin/routes'
        ];

        $locales = $this->getAvailableLocales();

        foreach ($locales as $locale) {
            foreach ($adminPages as $page) {
                $this->requestWithLocale('GET', $page, [], [], [], null, $locale);
                
                $this->assertResponseIsSuccessful(
                    "Admin page {$page} failed for locale {$locale}"
                );
            }
        }
    }
}