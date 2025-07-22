<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\User;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class BaseWebTestCase extends WebTestCase
{
    protected ?KernelBrowser $client = null;
    protected ?EntityManagerInterface $entityManager;
    private static bool $schemaCreated = false;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    /**
     * Creates a URL with locale
     */
    protected function getLocalizedUrl(string $path, string $locale = 'ru'): string
    {
        $path = ltrim($path, '/');
        
        if (str_contains($path, '?')) {
            [$pathPart, $queryPart] = explode('?', $path, 2);
            return sprintf('/%s/%s?%s', '', $pathPart, $queryPart);
        }
        
        return sprintf('/%s/%s', $locale, $path);
    }

    /**
     * Performs a request with locale
     */
    protected function requestWithLocale(
        string $method, 
        string $path, 
        array $parameters = [], 
        array $files = [], 
        array $server = [], 
        ?string $content = null, 
        string $locale = 'ru'
    ): void {
        $this->client->request($method, $this->getLocalizedUrl($path, $locale), $parameters, $files, $server, $content);
    }

    protected function createTestUser(
        string $email,
        array $roles = [Role::USER],
        bool $confirmed = true
    ): User {
        static $userCounter = 0;
        $userCounter++;
        $emailParts = explode('@', $email);
        $uniqueEmail = $emailParts[0] . '+' . $userCounter . '@' . $emailParts[1];

        $user = new User();
        $user->setEmail($uniqueEmail);
        $user->setPassword('$2y$13$hashedpassword');
        $user->setIsConfirmed($confirmed);
        $user->setRoles($roles);

        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        $uniquePhoneNumber = $phoneUtil->parse('+37529' . (1000000 + $userCounter), 'BY');
        $user->setPhone($uniquePhoneNumber);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function createAdminUser(string $email = 'admin@test.com'): User
    {
        return $this->createTestUser($email, [Role::USER, Role::ADMIN], true);
    }

    protected function createRegularUser(string $email = 'user@test.com'): User
    {
        return $this->createTestUser($email, [Role::USER], false);
    }

    protected function getAvailableLocales(): array
    {
        $localesString = $this->client->getContainer()->getParameter('app.locales');
        return explode('|', $localesString);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->entityManager && $this->entityManager->isOpen()) {
            $connection = $this->entityManager->getConnection();
            $schemaManager = $connection->createSchemaManager();
            $tables = $schemaManager->listTables();

            $connection->executeStatement('PRAGMA foreign_keys = OFF');
            foreach ($tables as $table) {
                $tableName = $table->getName();
                if ($tableName === 'sqlite_sequence') {
                    continue;
                }
                $connection->executeStatement('DELETE FROM ' . $connection->quoteIdentifier($tableName));
            }
            $connection->executeStatement('PRAGMA foreign_keys = ON');

            $this->entityManager->clear();
        }

        if ($this->entityManager && $this->entityManager->isOpen()) {
            $this->entityManager->close();
        }
        $this->entityManager = null;
        
        $this->client = null;
    }
}