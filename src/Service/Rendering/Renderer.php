<?php

declare(strict_types=1);

namespace App\Service\Rendering;

use App\DTO\AbstractEmailDTO;
use App\Enum\EmailTemplate;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Abstract base class for rendering services.
 *
 * This class provides common dependencies and abstract methods for rendering
 * various types of content, particularly for emails. Concrete implementations
 * will define the specific rendering logic.
 */
abstract class Renderer 
{
    /**
     * Constructs a new Renderer instance.
     *
     * @param Environment $twig The Twig rendering environment.
     * @param TranslatorInterface $translator The Symfony translator service.
     * @param string $senderEmail The default sender email address for communications.
     * @param string $siteName The name of the website or application.
     * @param string $siteUrl The base URL of the website or application.
     */
    public function __construct(
        protected Environment $twig,
        protected TranslatorInterface $translator,
        protected string $senderEmail,
        protected string $siteName,
        protected string $siteUrl
    ) {
    }

    /**
     * Renders content based on an AbstractEmailDTO.
     *
     * This abstract method must be implemented by concrete renderer classes
     * to define how an email-specific DTO is processed into renderable content.
     *
     * @param AbstractEmailDTO $dto The DTO containing email-specific data and template information.
     * @param array<string, mixed> $context Optional array of additional variables to pass to the template.
     * @return array<string, string> An associative array of rendered content parts (e.g., 'subject', 'html_content', 'text_content').
     */
    abstract public function renderFromDTO(AbstractEmailDTO $dto, array $context = []): array;

    /**
     * Renders content using a specific EmailTemplate.
     *
     * This abstract method must be implemented by concrete renderer classes
     * to define how a given `EmailTemplate` enum and context are used to
     * produce the final rendered output.
     *
     * @param EmailTemplate $template The EmailTemplate enum specifying the template and subject key.
     * @param array<string, mixed> $context An associative array of variables to pass to the Twig templates.
     * @return array<string, string> An associative array of rendered content parts (e.g., 'subject', 'html_content', 'text_content').
     */
    abstract public function render(EmailTemplate $template, array $context = []): array;
}