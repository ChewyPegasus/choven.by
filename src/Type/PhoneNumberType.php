<?php

declare(strict_types=1);

namespace App\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;

class PhoneNumberType extends Type
{
    const PHONE_NUMBER = 'phone_number';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?PhoneNumber
    {
        if ($value === null) {
            return null;
        }

        $phoneUtil = PhoneNumberUtil::getInstance();
        try {
            return $phoneUtil->parse($value, null);
        } catch (NumberParseException $e) {
            return null;
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PhoneNumber) {
            $phoneUtil = PhoneNumberUtil::getInstance();
            
            return $phoneUtil->format($value, \libphonenumber\PhoneNumberFormat::E164);
        }

        return $value;
    }

    public function getName(): string
    {
        return self::PHONE_NUMBER;
    }
}