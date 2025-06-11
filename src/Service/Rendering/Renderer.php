<?php

declare(strict_types=1);

namespace App\Service\Rendering;

use App\Enum\EmailTemplate;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

abstract class Renderer 
{
    public function __construct(
        protected Environment $twig,
        protected TranslatorInterface $translator,
        protected string $senderEmail,
        protected string $siteName,
        protected string $siteUrl
    )
    {
    }

    abstract public function render(EmailTemplate $template, object $entity, array $context = []): array;
}
