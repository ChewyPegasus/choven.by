<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * Custom validation constraint for phone numbers.
 *
 * This constraint is used to validate if a given string represents a valid phone number.
 * It allows specifying a default region for parsing numbers that do not include a country code.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class PhoneNumber extends Constraint
{
    /**
     * @var string The default error message displayed when the phone number is invalid.
     */
    public string $message = 'registration.form.error.phone_invalid';

    /**
     * @var string|null The default region code (ISO 3166-1 alpha-2) to use when parsing
     * phone numbers that do not contain an explicit country code.
     * For example, 'BY' for Belarus. If null, libphonenumber will attempt
     * to infer the region.
     */
    public ?string $defaultRegion = 'BY';
}