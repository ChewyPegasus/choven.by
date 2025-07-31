<?php

declare(strict_types=1);

namespace App\Service\Rendering;

use App\DTO\AbstractEmailDTO;
use App\Enum\EmailTemplate;
use Twig\Environment; // Import Twig Environment
use Symfony\Contracts\Translation\TranslatorInterface; // Import TranslatorInterface

/**
 * Service for rendering email templates.
 *
 * This class extends a base `Renderer` (assumed to contain common rendering logic,
 * Twig environment, and translator) and specializes in rendering email content
 * (HTML and plain text) along with subject, sender email, and sender name,
 * based on `AbstractEmailDTO` objects and `EmailTemplate` enums.
 */
class EmailRenderer extends Renderer
{
    /**
     * Renders an email based on an AbstractEmailDTO.
     *
     * This method prepares the context for rendering by merging common site-related
     * variables with the DTO and any additional context. It then delegates to the
     * `render` method to produce the final email parts.
     *
     * @param AbstractEmailDTO $dto The DTO containing email-specific data and template information.
     * @param array<string, mixed> $additionalContext Optional array of additional variables to pass to the template.
     * @return array<string, string> An associative array containing 'subject', 'html_content', 'text_content', 'sender_email', and 'sender_name'.
     */
    public function renderFromDTO(AbstractEmailDTO $dto, array $additionalContext = []): array
    {
        $template = $dto->getEmailTemplate();
        $context = array_merge(
            [
                'dto' => $dto,
                'site_name' => $this->siteName,
                'site_url' => $this->siteUrl,
            ],
            $dto->getContext(), // Merge DTO's specific context first
            $additionalContext // Allow additional context to override
        );

        return $this->render($template, $context);
    }

    /**
     * Renders a specific email template.
     *
     * This method uses Twig to render both the HTML and plain text versions of the email,
     * and fetches the translated subject and sender name.
     *
     * @param EmailTemplate $template The EmailTemplate enum specifying the template and subject key.
     * @param array<string, mixed> $context An associative array of variables to pass to the Twig templates.
     * @return array<string, string> An associative array containing:
     * - 'subject': The translated email subject.
     * - 'html_content': The rendered HTML content of the email.
     * - 'text_content': The rendered plain text content of the email.
     * - 'sender_email': The configured sender email address.
     * - 'sender_name': The translated sender name.
     */
    public function render(EmailTemplate $template, array $context = []): array
    {
        // Render HTML content using the HTML Twig template path from the EmailTemplate enum
        $htmlContent = $this->twig->render($template->getHtmlTemplate(), $context);
        
        // Render plain text content using the Text Twig template path from the EmailTemplate enum
        $textContent = $this->twig->render($template->getTextTemplate(), $context);

        return [
            'subject' => $this->translator->trans($template->getSubjectKey()),
            'html_content' => $htmlContent,
            'text_content' => $textContent,
            'sender_email' => $this->senderEmail,
            'sender_name' => $this->translator->trans('email.sender_name'), // Translate sender name
        ];
    }
}