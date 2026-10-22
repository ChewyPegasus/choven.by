<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GiftCertificate;
use App\Mailer\AppMailer;
use App\Repository\GiftCertificateRepository;

/**
 * Creating gift certificates: ordered on the site (paid online) or issued by the team (sold offline, prizes).
 */
class GiftCertificateService
{
    public function __construct(
        private readonly GiftCertificateRepository $certificates,
        private readonly AppMailer $mailer,
    ) {
    }

    /**
     * Saves a certificate ordered on the site; it becomes valid after the payment.
     */
    public function order(GiftCertificate $gift, string $locale): void
    {
        $gift->setLocale($locale);
        $this->ensureUniqueCode($gift);
        $this->certificates->save($gift);
    }

    /**
     * A certificate issued from the admin panel is valid right away.
     */
    public function issue(GiftCertificate $gift, bool $sendEmail): void
    {
        $gift->activate();
        $this->ensureUniqueCode($gift);
        $this->certificates->save($gift);

        if ($sendEmail) {
            $this->mailer->sendGiftCertificate($gift);
        }
    }

    private function ensureUniqueCode(GiftCertificate $gift): void
    {
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $existing = $this->certificates->findOneByCode($gift->getCode());
            if (null === $existing || $existing === $gift) {
                return;
            }
            $gift->regenerateCode();
        }

        throw new \RuntimeException('Could not generate a unique gift certificate code.');
    }
}
