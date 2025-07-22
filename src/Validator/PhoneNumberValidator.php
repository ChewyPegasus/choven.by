<?php

declare(strict_types=1);

namespace App\Validator;

use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber as LibPhoneNumber;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class PhoneNumberValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof PhoneNumber) {
            throw new UnexpectedTypeException($constraint, PhoneNumber::class);
        }

        // Allow null and empty values
        if (null === $value || '' === $value) {
            return;
        }

        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            // Handle both PhoneNumber objects and strings
            if ($value instanceof LibPhoneNumber) {
                // If it's already a PhoneNumber object, validate it directly
                $isValid = $phoneUtil->isValidNumber($value);
            } elseif (is_string($value)) {
                // If it's a string, parse it first
                $phoneNumber = $phoneUtil->parse($value, $constraint->defaultRegion);
                $isValid = $phoneUtil->isValidNumber($phoneNumber);
            } else {
                // Unsupported type
                throw new UnexpectedTypeException($value, 'string or libphonenumber\PhoneNumber');
            }

            if (!$isValid) {
                $displayValue = $value instanceof LibPhoneNumber 
                    ? $phoneUtil->format($value, \libphonenumber\PhoneNumberFormat::INTERNATIONAL)
                    : (string) $value;
                    
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $displayValue)
                    ->addViolation();
            }
        } catch (NumberParseException $e) {
            $displayValue = $value instanceof LibPhoneNumber 
                ? $phoneUtil->format($value, \libphonenumber\PhoneNumberFormat::INTERNATIONAL)
                : (string) $value;
                
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $displayValue)
                ->addViolation();
        }
    }
}