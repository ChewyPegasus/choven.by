<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Enum representing different types of email templates used in the application.
 *
 * Each case of this enum corresponds to a specific email type and provides
 * methods to retrieve the associated HTML template path, plain text template path,
 * and the translation key for the email subject.
 */
enum EmailTemplate: string
{
    /**
     * Represents the email template for order confirmation.
     */
    case ORDER_CONFIRMATION = 'order_confirmation';

    /**
     * Represents the email template for user email verification.
     */
    case VERIFICATION = 'verification';

    /**
     * Returns the path to the HTML Twig template for the current email type.
     *
     * @return string The path to the HTML email template.
     */
    public function getHtmlTemplate(): string
    {
        return match($this) {
            self::ORDER_CONFIRMATION => "mailer/order/index.html.twig",
            self::VERIFICATION => "mailer/verification/index.html.twig",
        };
    }

    /**
     * Returns the path to the plain text Twig template for the current email type.
     *
     * @return string The path to the plain text email template.
     */
    public function getTextTemplate(): string
    {
        return match($this) {
            self::ORDER_CONFIRMATION => 'mailer/order/index.txt.twig',
            self::VERIFICATION => 'mailer/verification/index.txt.twig',
        };
    }

    /**
     * Returns the translation key for the subject line of the current email type.
     *
     * This key can be used with a Symfony Translator to get the localized subject.
     *
     * @return string The translation key for the email subject.
     */
    public function getSubjectKey(): string
    {
        return match($this) {
            self::ORDER_CONFIRMATION => 'email.order_confirmation.subject',
            self::VERIFICATION => 'email.verification.subject',
        };
    }
}