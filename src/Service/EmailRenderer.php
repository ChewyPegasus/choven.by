<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\EmailTemplate;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class EmailRenderer
{
    public function __construct(
        private Environment $twig,
        private TranslatorInterface $translator,
        private string $senderEmail,
        private string $siteName,
        private string $siteUrl
    )
    {
    }

    public function render(EmailTemplate $template, object $entity, array $context = []): array
    {
        $context = array_merge([
            'entity' => $entity,
            'site_name' => $this->siteName,
            'site_url' => $this->siteUrl,
        ], $context);

        if (method_exists($entity, 'getId') && property_exists($entity, 'email')) {
            $context['order'] = $entity;
        }

        $htmlContent = $this->twig->render($template->getHtmlTemplate(), $context);
        $textContent = $this->twig->render($template->getTextTemplate(), $context);
        
        return [
            'subject' => $this->translator->trans($template->getSubjectKey()),
            'html_content' => $htmlContent,
            'text_content' => $textContent,
            'sender_email' => $this->senderEmail,
            'sender_name' => $this->translator->trans('email.sender_name'),
        ];
    }
}