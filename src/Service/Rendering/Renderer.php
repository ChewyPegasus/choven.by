<?php

declare(strict_types=1);

namespace App\Service\Rendering;

use App\DTO\AbstractEmailDTO;
use App\DTO\DTO;
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

    abstract public function renderFromDTO(AbstractEmailDTO $dto, array $context = []): array;

    abstract public function render(EmailTemplate $template, array $context = []): array;
}
