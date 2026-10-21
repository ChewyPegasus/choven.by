<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\PhoneNumber;
use App\Validator\PhoneNumberValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<PhoneNumberValidator>
 */
final class PhoneNumberValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): PhoneNumberValidator
    {
        return new PhoneNumberValidator();
    }

    public function testValidBelarusianNumbersInDifferentFormats(): void
    {
        foreach (['+375 29 706-75-85', '8 029 706 75 85', '+375297067585', '+48 601 234 567'] as $number) {
            $this->validator->validate($number, new PhoneNumber());
        }
        $this->assertNoViolation();
    }

    public function testEmptyValueIsAllowed(): void
    {
        $this->validator->validate('', new PhoneNumber());
        $this->validator->validate(null, new PhoneNumber());
        $this->assertNoViolation();
    }

    public function testInvalidNumber(): void
    {
        $this->validator->validate('12345', new PhoneNumber());
        $this->buildViolation('phone.invalid')->assertRaised();
    }
}
