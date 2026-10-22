<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GiftCertificate;
use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

/**
 * Printable A5 gift certificate (PDF, rendered from a Twig template with dompdf).
 */
class GiftCertificatePdf
{
    public function __construct(
        private readonly Environment $twig,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.cache_dir%/dompdf')]
        private readonly string $fontCacheDir,
    ) {
    }

    /**
     * PDF contents, or null when rendering failed (the email is then sent without the attachment).
     */
    public function render(GiftCertificate $gift): ?string
    {
        try {
            $html = $this->localeSwitcher->runWithLocale($gift->getLocale(), fn (): string => $this->twig->render('gift/pdf.html.twig', ['gift' => $gift]));

            if (!is_dir($this->fontCacheDir)) {
                @mkdir($this->fontCacheDir, 0o775, true);
            }

            $options = new Options();
            // DejaVu Sans ships with dompdf and covers Cyrillic (incl. Belarusian ў, і).
            $options->setDefaultFont('DejaVu Sans');
            $options->setIsRemoteEnabled(false);
            $options->setIsPhpEnabled(false);
            $options->setFontCache($this->fontCacheDir);
            $options->setTempDir(sys_get_temp_dir());

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A5', 'landscape');
            $dompdf->render();

            return $dompdf->output();
        } catch (\Throwable $e) {
            $this->logger->error('Could not render gift certificate {code}: {message}', ['code' => $gift->getCode(), 'message' => $e->getMessage()]);

            return null;
        }
    }
}
