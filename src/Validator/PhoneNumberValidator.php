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

        // Разрешаем null и пустые значения
        if (null === $value || '' === $value) {
            return;
        }

        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            // Обрабатываем и PhoneNumber объекты, и строки
            if ($value instanceof LibPhoneNumber) {
                // Если это уже объект PhoneNumber, проверяем его напрямую
                $isValid = $phoneUtil->isValidNumber($value);
            } elseif (is_string($value)) {
                // Если это строка, парсим её сначала
                $phoneNumber = $phoneUtil->parse($value, $constraint->defaultRegion);
                $isValid = $phoneUtil->isValidNumber($phoneNumber);
            } else {
                // Неподдерживаемый тип
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