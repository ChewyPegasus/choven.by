<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AuditLog;
use App\Entity\Extra;
use App\Entity\FaqItem;
use App\Entity\Post;
use App\Entity\PromoCode;
use App\Entity\User;
use App\Service\ImageVariants;
use App\Service\SiteSettings;
use App\Tests\Support\WebTestCase;
use OTPHP\TOTP;
use Symfony\Component\Clock\NativeClock;

/**
 * Two-factor authentication, the audit log, and the new admin sections.
 */
final class AdminFeaturesTest extends WebTestCase
{
    public function testTwoFactorAuthentication(): void
    {
        $user = $this->factory->user('anna@example.com');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/ru/profile/security');
        self::assertResponseIsSuccessful();
        $this->submit($crawler, 'Включить');
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('.qr-box img[src^="data:image/svg+xml;base64,"]');

        $secret = $this->reload($user)->getTotpSecret();
        if (null === $secret || '' === $secret) {
            self::fail('A TOTP secret must be generated.');
        }
        $this->submit($crawler, 'Подтвердить', ['code' => '000000']);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Неверный код');

        $this->submit($crawler, 'Подтвердить', ['code' => TOTP::createFromSecret($secret, new NativeClock())->now()]);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Двухфакторная аутентификация включена');
        $codes = $crawler->filter('.backup-codes code')->each(static fn ($node): string => $node->text());
        self::assertCount(10, $codes);
        self::assertTrue($this->reload($user)->isTotpAuthenticationEnabled());

        // Next sign-in asks for the code after the password; a backup code works once.
        $this->client->request('GET', '/ru/logout');
        $this->client->restart();
        $this->client->setServerParameter('HTTP_SEC_FETCH_SITE', 'same-origin');
        $crawler = $this->client->request('GET', '/ru/login');
        $this->submit($crawler, 'Войти', ['identifier' => 'anna@example.com', 'password' => 'secret-password']);
        $this->client->followRedirect();
        self::assertResponseRedirects('http://localhost/ru/2fa', message: 'nothing is accessible before the second step');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Подтверждение входа');

        $this->submit($crawler, 'Войти', ['_auth_code' => '123456']);
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('.alert-error', 'a wrong code is refused');

        $this->submit($crawler, 'Войти', ['_auth_code' => $codes[0]]);
        self::assertResponseRedirects();
        $this->client->request('GET', '/ru/profile/orders');
        self::assertResponseIsSuccessful();
        self::assertSame(9, $this->reload($user)->countBackupCodes());
    }

    public function testAdministratorsCanBeRequiredToUseTwoFactor(): void
    {
        $admin = $this->factory->admin();
        $this->settings([SiteSettings::ADMIN_2FA_REQUIRED => true]);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/ru/admin');
        self::assertResponseRedirects('/ru/profile/security#two-factor');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'включите двухфакторную аутентификацию');
    }

    public function testAdminChangesAreAudited(): void
    {
        $admin = $this->factory->admin();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/ru/admin/promo-codes/new');
        $this->submit($crawler, 'Сохранить', [
            'promo_code[code]' => 'autumn15',
            'promo_code[type]' => 'percent',
            'promo_code[value]' => '15',
            'promo_code[isActive]' => '1',
        ]);
        self::assertResponseRedirects('/ru/admin/promo-codes');
        $promo = $this->em->getRepository(PromoCode::class)->findOneBy(['code' => 'AUTUMN15']);
        self::assertInstanceOf(PromoCode::class, $promo);

        $crawler = $this->client->request('GET', '/ru/admin/promo-codes/'.$promo->getId().'/edit');
        $this->submit($crawler, 'Сохранить', ['promo_code[value]' => '20']);

        $entries = $this->em->getRepository(AuditLog::class)->findBy(['entityType' => 'PromoCode'], ['id' => 'ASC']);
        self::assertCount(2, $entries);
        self::assertSame('create', $entries[0]->getAction());
        self::assertSame('admin@example.com', $entries[0]->getUserEmail());
        self::assertSame('update', $entries[1]->getAction());
        self::assertSame(['value' => [15, 20]], $entries[1]->getChanges());
        self::assertSame('AUTUMN15', $entries[1]->getLabel());

        $this->client->request('GET', '/ru/admin/audit?type=PromoCode');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.audit-table', 'AUTUMN15');

        // Passwords never end up in the log.
        $user = $this->factory->user('guest@example.com');
        $crawler = $this->client->request('GET', '/ru/admin/users/'.$user->getId().'/edit');
        $this->submit($crawler, 'Сохранить', ['user_admin[newPassword]' => 'new-secret-123']);
        $userEntry = $this->em->getRepository(AuditLog::class)->findOneBy(['entityType' => 'User', 'action' => 'update']);
        self::assertSame('***', $userEntry?->getChanges()['password'] ?? null);
    }

    public function testContentSections(): void
    {
        $this->client->loginUser($this->factory->admin());

        $crawler = $this->client->request('GET', '/ru/admin/extras/new');
        $this->submit($crawler, 'Сохранить', [
            'extra[name][ru]' => 'Трансфер',
            'extra[price]' => '25',
            'extra[unit]' => 'person',
            'extra[maxQuantity]' => '10',
            'extra[position]' => '1',
            'extra[isActive]' => '1',
        ]);
        self::assertResponseRedirects('/ru/admin/extras');
        self::assertNotNull($this->em->getRepository(Extra::class)->findOneBy(['price' => 25]));

        $crawler = $this->client->request('GET', '/ru/admin/posts/new');
        $this->submit($crawler, 'Сохранить', [
            'post[title][ru]' => 'Как выбрать реку',
            'post[slug]' => 'how-to-choose',
            'post[body][ru]' => "## Советы\n\nВыбирайте **Ислочь**.",
            'post[isPublished]' => '1',
        ]);
        self::assertResponseRedirects('/ru/admin/posts');

        $crawler = $this->client->request('GET', '/ru/blog/how-to-choose');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.prose h2', 'Советы');
        self::assertStringContainsString('"@type":"BlogPosting"', $crawler->filter('head')->html());

        $this->client->request('GET', '/ru/blog');
        self::assertSelectorTextContains('.tour-grid', 'Как выбрать реку');

        $draft = (new Post())->setSlug('draft')->setTitle(['ru' => 'Черновик'])->setBody(['ru' => 'Текст']);
        $this->em->persist($draft);
        $this->em->flush();
        $this->client->request('GET', '/ru/blog/draft');
        self::assertResponseStatusCodeSame(404);
    }

    public function testFaqFromTheDatabase(): void
    {
        $items = $this->em->getRepository(FaqItem::class)->findAll();
        self::assertGreaterThanOrEqual(7, \count($items), 'seeded by the migration');

        $crawler = $this->client->request('GET', '/en/faq');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.accordion', 'Do I need any experience');
        self::assertStringContainsString('"@type":"FAQPage"', $crawler->filter('head')->html());

        $this->client->request('GET', '/sitemap.xml');
        self::assertStringContainsString('/ru/faq', (string) $this->client->getResponse()->getContent());
    }

    public function testResponsiveImageVariants(): void
    {
        if (!\function_exists('imagewebp')) {
            self::markTestSkipped('GD with WebP is not available.');
        }

        $dir = static::getContainer()->getParameter('kernel.project_dir').'/public/uploads/test';
        @mkdir($dir, 0o777, true);
        $image = imagecreatetruecolor(1000, 600);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 11, 114, 133));
        imagejpeg($image, $dir.'/photo.jpg');
        $variants = static::getContainer()->get(ImageVariants::class);

        try {
            self::assertGreaterThan(0, $variants->generate('uploads/test/photo.jpg'));
            $info = $variants->describe('uploads/test/photo.jpg');

            self::assertNotNull($info);
            self::assertSame(1000, $info['width']);
            self::assertSame(600, $info['height']);
            self::assertStringContainsString('/uploads/_v/test/photo-480.webp 480w', $info['sources']['image/webp']);
            self::assertStringContainsString('photo-1000.webp 1000w', $info['sources']['image/webp']);
        } finally {
            $variants->remove('uploads/test/photo.jpg');
            @unlink($dir.'/photo.jpg');
            @rmdir($dir);
        }
    }

    public function testUserIdentifierIsTheEmail(): void
    {
        // Guards the 2FA "username" shown in authenticator apps.
        self::assertSame('anna@example.com', (new User())->setEmail('Anna@Example.com')->getTotpAuthenticationUsername());
    }
}
