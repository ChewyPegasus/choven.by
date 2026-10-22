<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Payment;
use App\Entity\Post;
use App\Entity\Review;
use App\Entity\WaitlistEntry;
use App\Enum\ExtraUnit;
use App\Enum\OrderStatus;
use App\Enum\ReviewStatus;
use App\Service\SiteSettings;
use App\Tests\Support\WebTestCase;

/**
 * Renders every page in every language and fails when a raw translation key ("booking.form.name")
 * leaks into the HTML, which catches dynamic keys that static extraction cannot see.
 */
final class TranslationCoverageTest extends WebTestCase
{
    private const KEY_PATTERN = '/(?<![\w\/.#@-])(?:a11y|about|admin|blog|booking|contact|email|error|extra|faq|footer|form|gift|ics|info|legal|locale|login|main|map|messenger|nav|order|package|pagination|payment|phone|policy|price|profile|promo|registration|reschedule|reset_password|review|river|route|seo|tour|twofa|user|verification|waitlist|weather|flash)\.[a-z0-9_]+(?:\.[a-z0-9_]+)*(?![\w\/-])/';

    public function testNoUntranslatedKeysOnPublicAndAdminPages(): void
    {
        $this->settings([SiteSettings::PREPAYMENT_PERCENT => 30]);
        $admin = $this->factory->admin();
        $this->factory->extra('Трансфер', 25, ExtraUnit::PER_PERSON);
        $this->factory->extra('Палатка', 15, ExtraUnit::PER_ITEM, perDay: true);
        $this->factory->promo();
        $gift = $this->factory->gift();
        $order = $this->factory->order('+8 days', days: 2, status: OrderStatus::CONFIRMED, user: $admin);
        $order->setDepositAmount(100);
        $past = $this->factory->order('-4 days', status: OrderStatus::COMPLETED, user: $admin);
        $review = (new Review())->setAuthorName('Ольга')->setRating(4)->setText('Очень понравилось, приедем ещё!')->setStatus(ReviewStatus::APPROVED);
        $post = (new Post())->setSlug('first-trip')->setTitle(['ru' => 'Первый сплав'])->setBody(['ru' => "## Советы\n\nТекст"])->setIsPublished(true);
        $payment = (new Payment('demo', 100))->setOrder($order);
        $waiting = (new WaitlistEntry())->setStartDate(new \DateTimeImmutable('+9 days'))->setName('Иван')->setEmail('ivan@example.com');
        foreach ([$review, $post, $payment, $waiting] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        $public = ['/', '/info', '/about', '/booking', '/booking?tour=isloch-overnight', '/tours/isloch-overnight', '/contact', '/privacy', '/terms',
            '/login', '/register', '/register/check-email', '/reset-password', '/verify/resend', '/faq', '/blog', '/blog/first-trip', '/gift',
            '/gift/'.$gift->getToken(), '/payment/demo/'.$payment->getPublicId(),
            '/booking/'.$order->getAccessToken(), '/booking/'.$order->getAccessToken().'/reschedule',
            '/booking/'.$past->getAccessToken(), '/booking/'.$past->getAccessToken().'/review'];
        $private = ['/profile/orders', '/profile/settings', '/profile/security', '/admin', '/admin/orders', '/admin/orders/new', '/admin/orders/'.$order->getId().'/edit',
            '/admin/orders/calendar', '/admin/orders/day/'.$order->getStartDate()?->format('Y-m-d'), '/admin/users', '/admin/users/new', '/admin/users/'.$admin->getId().'/edit', '/admin/tours', '/admin/tours/new',
            '/admin/tours/1/edit', '/admin/routes', '/admin/routes/new', '/admin/routes/1/edit', '/admin/reviews?status=all', '/admin/requests',
            '/admin/settings', '/admin/emails', '/admin/logs', '/admin/payments', '/admin/promo-codes', '/admin/promo-codes/new',
            '/admin/gift-certificates', '/admin/gift-certificates/new', '/admin/extras', '/admin/extras/new', '/admin/posts', '/admin/posts/new',
            '/admin/posts/'.$post->getId().'/edit', '/admin/faq', '/admin/faq/new', '/admin/faq/1/edit', '/admin/waitlist', '/admin/audit'];

        $problems = [];
        foreach (['ru', 'be', 'en'] as $locale) {
            foreach ($public as $path) {
                $problems += $this->check('/'.$locale.$path);
            }
        }

        $this->client->loginUser($admin);
        foreach (['ru', 'be', 'en'] as $locale) {
            foreach ($private as $path) {
                $problems += $this->check('/'.$locale.$path);
            }
        }

        self::assertSame([], $problems, 'Untranslated keys found');
    }

    /**
     * @return array<string, list<string>>
     */
    private function check(string $url): array
    {
        $crawler = $this->client->request('GET', $url);
        $status = $this->client->getResponse()->getStatusCode();
        if ($status >= 300 && $status < 400) {
            $crawler = $this->client->followRedirect();
        }
        self::assertLessThan(400, $this->client->getResponse()->getStatusCode(), $url);

        // Visible text plus the attributes people can see (placeholders, titles, labels).
        $text = $crawler->filter('body')->text('', true);
        foreach ($crawler->filter('[placeholder], [title], [aria-label], [data-turbo-confirm], option') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            foreach (['placeholder', 'title', 'aria-label', 'data-turbo-confirm'] as $attribute) {
                $text .= ' '.$node->getAttribute($attribute);
            }
        }

        preg_match_all(self::KEY_PATTERN, $text, $matches);
        $keys = array_values(array_unique(array_filter($matches[0], static fn (string $k): bool => !str_ends_with($k, '.by') && !str_ends_with($k, '.twig'))));

        return [] === $keys ? [] : [$url => $keys];
    }
}
