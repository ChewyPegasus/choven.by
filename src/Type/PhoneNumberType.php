<?php

declare(strict_types=1);

namespace App\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;

/**
 * Custom Doctrine DBAL type for storing and retrieving phone numbers.
 *
 * This type converts `libphonenumber\PhoneNumber` objects to their E.164 string
 * representation for storage in the database and converts E.164 strings back
 * into `PhoneNumber` objects when retrieved from the database.
 */
class PhoneNumberType extends Type
{
    /**
     * @var string The name of the custom DBAL type.
     */
    public const PHONE_NUMBER = 'phone_number';

    /**
     * Gets the SQL declaration for the column.
     *
     * Defines the database column type that will be used to store phone numbers.
     * It's typically a string type with a sufficient length for E.164 format.
     *
     * @param array<string, mixed> $column The column definition array.
     * @param AbstractPlatform $platform The currently used database platform.
     * @return string The SQL declaration for the column.
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    /**
     * Converts a database value to its PHP representation.
     *
     * This method transforms a string (expected to be an E.164 phone number)
     * retrieved from the database into a `libphonenumber\PhoneNumber` object.
     * If the value is null or parsing fails, it returns null.
     *
     * @param mixed $value The value retrieved from the database.
     * @param AbstractPlatform $platform The currently used database platform.
     * @return PhoneNumber|null The `PhoneNumber` object, or null if the value is null or invalid.
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?PhoneNumber
    {
        if ($value === null) {
            return null;
        }

        $phoneUtil = PhoneNumberUtil::getInstance();
        try {
            // Attempt to parse the string into a PhoneNumber object.
            // Passing null as the default region allows the utility to infer from the number itself.
            return $phoneUtil->parse($value, null);
        } catch (NumberParseException $e) {
            // If parsing fails, return null. Logging the error might be beneficial in a real application.
            return null;
        }
    }

    /**
     * Converts a PHP value to its database representation.
     *
     * This method transforms a `libphonenumber\PhoneNumber` object into its
     * E.164 string format for storage in the database. If the value is null,
     * it returns null. If it's already a string, it's returned as is.
     *
     * @param mixed $value The PHP value to convert. Expected to be `PhoneNumber` object or null.
     * @param AbstractPlatform $platform The currently used database platform.
     * @return string|null The E.164 formatted phone number string, or null.
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PhoneNumber) {
            $phoneUtil = PhoneNumberUtil::getInstance();
            // Format the PhoneNumber object to E.164 international standard for database storage.
            return $phoneUtil->format($value, \libphonenumber\PhoneNumberFormat::E164);
        }

        // If the value is already a string (e.g., already in E.164 format from a previous operation),
        // return it directly. This might happen if the entity's property was set with a string directly.
        return $value;
    }

    /**
     * Gets the name of this custom type.
     *
     * This name is used to register and refer to this type in Doctrine mappings.
     *
     * @return string The name of this type.
     */
    public function getName(): string
    {
        return self::PHONE_NUMBER;
    }

    /**
     * Checks if this type requires an SQL comment hint.
     *
     * This method indicates whether Doctrine should add a comment to the SQL
     * schema generated for this type, which can be useful for debugging or
     * identifying custom types in the database schema.
     *
     * @param AbstractPlatform $platform The currently used database platform.
     * @return bool True if an SQL comment hint is required, false otherwise.
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}