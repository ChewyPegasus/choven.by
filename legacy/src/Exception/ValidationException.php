<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown when validation fails.
 */
class ValidationException extends \Exception
{
    public function __construct(
        private readonly \Symfony\Component\Validator\ConstraintViolationListInterface $violations,
        string $message = 'Validation failed',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getViolations(): \Symfony\Component\Validator\ConstraintViolationListInterface
    {
        return $this->violations;
    }

    public function getViolationsAsString(): string
    {
        return (string) $this->violations;
    }
}