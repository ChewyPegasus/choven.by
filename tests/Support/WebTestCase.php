<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class WebTestCase extends BaseWebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;
    protected Factory $factory;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Modern browsers send this header; the stateless CSRF protection relies on it (or on the JS double-submit cookie).
        $this->client->disableReboot();
        $this->client->setServerParameter('HTTP_SEC_FETCH_SITE', 'same-origin');
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->factory = new Factory($this->em, $container->get(UserPasswordHasherInterface::class));
    }

    /**
     * Fresh copy of an entity from the database (the client resets the entity manager between requests).
     *
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    protected function reload(object $entity): object
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $id = $em->getClassMetadata($entity::class)->getIdentifierValues($entity);
        $em->clear();
        $fresh = $em->find($entity::class, $id);
        self::assertNotNull($fresh);

        return $fresh;
    }

    /**
     * Submits the first form that contains the given button.
     *
     * @param array<string, mixed> $values
     */
    protected function submit(Crawler $crawler, string $button, array $values = []): Crawler
    {
        $form = $crawler->selectButton($button)->form();

        return $this->client->submit($form, $values);
    }

    protected function email(int $index = 0): Email
    {
        $email = self::getMailerMessage($index);
        self::assertInstanceOf(Email::class, $email, 'No email was sent.');

        return $email;
    }

    protected function lastEmailUrl(string $pattern = '#https?://[^"\s<]+#'): string
    {
        $body = (string) $this->email()->getHtmlBody();
        self::assertSame(1, preg_match_all($pattern, html_entity_decode($body), $m) > 0 ? 1 : 0, 'No link found in the email.');

        return $m[0][0];
    }
}
