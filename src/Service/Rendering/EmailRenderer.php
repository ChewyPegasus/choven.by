<?php

declare(strict_types=1);

namespace App\Service\Rendering;

use App\DTO\AbstractEmailDTO;
use App\DTO\DTO;
use App\Enum\EmailTemplate;

class EmailRenderer extends Renderer
{
    public function renderFromDTO(AbstractEmailDTO $dto, array $additionalContext = []): array
    {
        $template = $dto->getEmailTemplate();
        $context = array_merge([
            'dto' => $dto,
            'site_name' => $this->siteName,
            'site_url' => $this->siteUrl,
        ], $additionalContext);

        return $this->render($template, $context);
    }

    public function render(EmailTemplate $template, array $context = []): array
    {
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
