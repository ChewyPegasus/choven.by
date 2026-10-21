<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\BaseWebTestCase;
use Symfony\Component\HttpFoundation\Response;

class SecurityControllerTest extends BaseWebTestCase
{
    public function testLoginPage(): void
    {
        $this->requestWithLocale('GET', '/login');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Вход');
    }

    public function testRegisterPage(): void
    {
        $this->requestWithLocale('GET', '/register');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Регистрация');
    }

    public function testSuccessfulLogin(): void
    {
        $user = $this->createTestUser('login@test.com', [], true);
        
        $passwordHasher = $this->client->getContainer()->get('security.user_password_hasher');
        $hashedPassword = $passwordHasher->hashPassword($user, 'password123');
        $user->setPassword($hashedPassword);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', $this->getLocalizedUrl('/login'));
        
        $form = $crawler->selectButton('Войти')->form([
            'email' => $user->getEmail(),
            'password' => 'password123'
        ]);

        $this->client->submit($form);
        
        // Check redirect (may be to the localized homepage)
        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('/', $location);
    }

    public function testFailedLogin(): void
    {
        $crawler = $this->client->request('GET', $this->getLocalizedUrl('/login'));
        
        $form = $crawler->selectButton('Войти')->form([
            'email' => 'wrong@test.com',
            'password' => 'wrongpassword'
        ]);

        $this->client->submit($form);
        
        // Check that we were redirected back to login
        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testSuccessfulRegistration(): void
    {
        $crawler = $this->client->request('GET', $this->getLocalizedUrl('/register'));
        
        $form = $crawler->selectButton('Зарегистрироваться')->form([
            'registration_form[email]' => 'newuser@test.com',
            'registration_form[phone]' => '+375447891162',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
            'registration_form[agreeTerms]' => true
        ]);

        $this->client->submit($form);
        
        $this->assertResponseRedirects();
        
        // Check that the user was created in the DB
        $user = $this->entityManager->getRepository(\App\Entity\User::class)
            ->findOneBy(['email' => 'newuser@test.com']);
        
        $this->assertNotNull($user);
        $this->assertFalse($user->isConfirmed());
        $this->assertNotNull($user->getConfirmationCode());
    }

    /**
     * Test locale independence
     */
    public function testLoginWorksWithAllLocales(): void
    {
        $locales = $this->getAvailableLocales();

        foreach ($locales as $locale) {
            $crawler = $this->client->request('GET', $this->getLocalizedUrl('/login', $locale));
            $this->assertResponseIsSuccessful("Login page failed for locale: {$locale}");
            
            $this->assertCount(1, $crawler->filter('#inputPassword'), "Password field not found for locale: {$locale}");
        }
    }
}