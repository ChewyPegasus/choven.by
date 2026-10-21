<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class PhoneNumber extends Constraint
{
    public string $message = 'phone.invalid';
    public string $defaultRegion = 'BY';

    #[HasNamedArguments]
    public function __construct(?string $message = null, ?string $defaultRegion = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->defaultRegion = $defaultRegion ?? $this->defaultRegion;
    }
}
