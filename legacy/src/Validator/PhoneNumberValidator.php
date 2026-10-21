<?php

declare(strict_types=1);

namespace App\Validator;

use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber as LibPhoneNumber; // Alias to avoid conflict with class name
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException; // More appropriate for value type mismatches

/**
 * Validator for the PhoneNumber constraint.
 *
 * This validator checks if a given value (string or `libphonenumber\PhoneNumber` object)
 * represents a valid phone number using the `libphonenumber` library.
 */
class PhoneNumberValidator extends ConstraintValidator
{
    /**
     * Validates the given value against the PhoneNumber constraint.
     *
     * @param mixed $value The value to validate (expected to be a string or `libphonenumber\PhoneNumber`).
     * @param Constraint $constraint The constraint being validated against (expected to be `PhoneNumber`).
     * @return void
     * @throws UnexpectedTypeException If the provided constraint is not an instance of `PhoneNumber`.
     * @throws UnexpectedValueException If the value is neither a string nor a `libphonenumber\PhoneNumber` object.
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof PhoneNumber) {
            throw new UnexpectedTypeException($constraint, PhoneNumber::class);
        }

        // Allow null and empty string values to pass validation.
        // If the field is required, a NotBlank constraint should be used separately.
        if (null === $value || '' === $value) {
            return;
        }

        $phoneUtil = PhoneNumberUtil::getInstance();
        $isValid = false; // Flag to track validation result
        $phoneNumber = null; // To hold the parsed PhoneNumber object if applicable

        try {
            // Handle both PhoneNumber objects and strings
            if ($value instanceof LibPhoneNumber) {
                // If it's already a PhoneNumber object, validate it directly
                $phoneNumber = $value;
                $isValid = $phoneUtil->isValidNumber($phoneNumber);
            } elseif (is_string($value)) {
                // If it's a string, parse it first using the default region from the constraint
                $phoneNumber = $phoneUtil->parse($value, $constraint->defaultRegion);
                $isValid = $phoneUtil->isValidNumber($phoneNumber);
            } else {
                // If the value is neither a string nor a PhoneNumber object, it's an unexpected type.
                throw new UnexpectedValueException($value, 'string or libphonenumber\PhoneNumber');
            }

            // If the number is not valid, add a violation
            if (!$isValid) {
                // Format the value for display in the error message
                $displayValue = $phoneNumber instanceof LibPhoneNumber 
                    ? $phoneUtil->format($phoneNumber, \libphonenumber\PhoneNumberFormat::INTERNATIONAL)
                    : (string) $value; // Fallback to string cast if parsing failed or it was initially a non-PhoneNumber string
                    
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $displayValue)
                    ->addViolation();
            }
        } catch (NumberParseException $e) {
            // Catch parsing exceptions from libphonenumber and add a validation violation
            $displayValue = $value instanceof LibPhoneNumber 
                ? ($phoneNumber ? $phoneUtil->format($phoneNumber, \libphonenumber\PhoneNumberFormat::INTERNATIONAL) : (string)$value)
                : (string) $value; // Ensure a string representation for the parameter
                
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $displayValue)
                ->addViolation();
        }
    }
}