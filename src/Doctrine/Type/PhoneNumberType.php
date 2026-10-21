<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Stores libphonenumber objects as E.164 strings (e.g. "+375297067585").
 */
final class PhoneNumberType extends Type
{
    public const NAME = 'phone_number';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PhoneNumber
    {
        if (null === $value || '' === $value || $value instanceof PhoneNumber) {
            return $value ?: null;
        }

        try {
            return PhoneNumberUtil::getInstance()->parse((string) $value, PhoneNumberUtil::UNKNOWN_REGION);
        } catch (NumberParseException) {
            return null;
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof PhoneNumber) {
            return PhoneNumberUtil::getInstance()->format($value, PhoneNumberFormat::E164);
        }

        if (\is_string($value)) {
            return $value;
        }

        throw InvalidType::new($value, self::NAME, ['null', 'string', PhoneNumber::class]);
    }
}
