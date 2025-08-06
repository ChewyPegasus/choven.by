<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * HTTP exception for validation errors.
 */
class ValidationHttpException extends HttpException
{
    public function __construct(
        private readonly \Symfony\Component\Validator\ConstraintViolationListInterface $violations,
        string $message = 'Validation failed',
        ?\Throwable $previous = null,
        array $headers = [],
        int $code = 0
    ) {
        parent::__construct(Response::HTTP_BAD_REQUEST, $message, $previous, $headers, $code);
    }

    public function getViolations(): \Symfony\Component\Validator\ConstraintViolationListInterface
    {
        return $this->violations;
    }
}