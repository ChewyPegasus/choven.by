<?php

declare(strict_types=1);

namespace App\Validator;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber as LibPhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class PhoneNumberValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PhoneNumber) {
            throw new UnexpectedTypeException($constraint, PhoneNumber::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $util = PhoneNumberUtil::getInstance();

        if (\is_string($value)) {
            try {
                $value = $util->parse($value, $constraint->defaultRegion);
            } catch (NumberParseException) {
                $this->context->buildViolation($constraint->message)->addViolation();

                return;
            }
        }

        if (!$value instanceof LibPhoneNumber) {
            throw new UnexpectedValueException($value, 'string|'.LibPhoneNumber::class);
        }

        if (!$util->isValidNumber($value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
