<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Review;
use App\Enum\OrderStatus;
use App\Enum\ReviewStatus;
use App\Tests\Support\WebTestCase;

/**
 * Renders every page in every language and fails when a raw translation key ("booking.form.name")
 * leaks into the HTML, which catches dynamic keys that static extraction cannot see.
 */
final class TranslationCoverageTest extends WebTestCase
{
    private const KEY_PATTERN = '/(?<![\w\/.#@-])(?:a11y|about|admin|booking|contact|email|error|footer|form|ics|info|legal|locale|login|main|map|nav|order|package|pagination|phone|profile|registration|reset_password|review|river|route|seo|tour|user|verification|flash)\.[a-z0-9_]+(?:\.[a-z0-9_]+)*(?![\w\/-])/';

    public function testNoUntranslatedKeysOnPublicAndAdminPages(): void
    {
        $admin = $this->factory->admin();
        $order = $this->factory->order('+4 days', days: 2, status: OrderStatus::CONFIRMED, user: $admin);
        $past = $this->factory->order('-4 days', status: OrderStatus::COMPLETED, user: $admin);
        $review = (new Review())->setAuthorName('Ольга')->setRating(4)->setText('Очень понравилось, приедем ещё!')->setStatus(ReviewStatus::APPROVED);
        $this->em->persist($review);
        $this->em->flush();

        $public = ['/', '/info', '/about', '/booking', '/booking?tour=isloch-overnight', '/tours/isloch-overnight', '/contact', '/privacy', '/terms',
            '/login', '/register', '/register/check-email', '/reset-password', '/verify/resend',
            '/booking/'.$order->getAccessToken(), '/booking/'.$past->getAccessToken(), '/booking/'.$past->getAccessToken().'/review'];
        $private = ['/profile/orders', '/profile/settings', '/admin', '/admin/orders', '/admin/orders/new', '/admin/orders/'.$order->getId().'/edit',
            '/admin/orders/calendar', '/admin/orders/day/'.$order->getStartDate()?->format('Y-m-d'), '/admin/users', '/admin/users/new', '/admin/users/'.$admin->getId().'/edit', '/admin/tours', '/admin/tours/new',
            '/admin/tours/1/edit', '/admin/routes', '/admin/routes/new', '/admin/routes/1/edit', '/admin/reviews?status=all', '/admin/requests',
            '/admin/settings', '/admin/emails', '/admin/logs'];

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
