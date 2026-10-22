<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

/**
 * Turning two-factor authentication (authenticator app + backup codes) on and off.
 */
class TwoFactorManager
{
    public const BACKUP_CODES = 10;

    public function __construct(
        private readonly TotpAuthenticatorInterface $totp,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Creates a new secret (2FA stays off until the first code is confirmed).
     */
    public function start(User $user): void
    {
        if ($user->isTotpAuthenticationEnabled()) {
            return;
        }

        $user->setTotpSecret($this->totp->generateSecret());
        $this->entityManager->flush();
    }

    /**
     * Enables 2FA when the code from the app is right; returns the backup codes to show once.
     *
     * @return list<string>|null null when the code is wrong
     */
    public function confirm(User $user, string $code): ?array
    {
        if (null === $user->getTotpSecret() || !$this->totp->checkCode($user, preg_replace('/\D/', '', $code) ?? '')) {
            return null;
        }

        $user->enableTotp();
        $codes = $this->regenerateBackupCodes($user);
        $this->audit->log('2fa_enabled', $user);

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->disableTotp();
        $this->entityManager->flush();
        $this->audit->log('2fa_disabled', $user);
    }

    /**
     * @return list<string> the new codes in clear text (only their hashes are stored)
     */
    public function regenerateBackupCodes(User $user): array
    {
        $codes = [];
        $hashes = [];
        for ($i = 0; $i < self::BACKUP_CODES; ++$i) {
            $code = strtolower(substr(bin2hex(random_bytes(5)), 0, 8));
            $codes[] = substr($code, 0, 4).'-'.substr($code, 4);
            $hashes[] = password_hash($code, \PASSWORD_DEFAULT);
        }

        $user->setBackupCodes($hashes);
        $this->entityManager->flush();

        return $codes;
    }

    public function checkCode(User $user, string $code): bool
    {
        return $user->isTotpAuthenticationEnabled() && $this->totp->checkCode($user, preg_replace('/\D/', '', $code) ?? '');
    }

    /**
     * QR code for the authenticator app, as an SVG data URI.
     */
    public function qrCode(User $user): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd()));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($this->totp->getQRContent($user)));
    }
}
