<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PhoneNumber extends Constraint
{
    public string $message = 'registration.form.error.phone_invalid';
    public ?string $defaultRegion = 'BY';
}