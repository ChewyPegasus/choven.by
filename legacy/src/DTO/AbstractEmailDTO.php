<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\EmailTemplate;

/**
 * Basic abstract class for all Data Transfer Objects (DTOs) related to email sending.
 *
 * This class defines the common interface for DTOs that encapsulate data
 * required for sending emails, ensuring that all concrete email DTOs
 * provide methods to retrieve the email template, recipient email address,
 * and the context data for the email.
 */
readonly abstract class AbstractEmailDTO implements DTO
{
    /**
     * Returns the EmailTemplate enum case associated with this DTO.
     *
     * This method specifies which email template should be used when sending
     * an email based on the data contained within this DTO.
     *
     * @return EmailTemplate The email template enum.
     */
    abstract public function getEmailTemplate(): EmailTemplate;

    /**
     * Returns the recipient's email address for the email.
     *
     * This method provides the primary email address to which the email
     * represented by this DTO should be sent.
     *
     * @return string The recipient's email address.
     */
    abstract public function getEmail(): string;

    /**
     * Returns the context data required for rendering the email template.
     *
     * This method provides an associative array of data that will be passed
     * to the email template for dynamic content generation.
     *
     * @return array An associative array of context data.
     */
    abstract public function getContext(): array;
}