<?php

declare(strict_types=1);

namespace App\EventListener;

use App\DTO\ApiResponse\ApiResponseDTO;
use App\Exception\ValidationException;
use App\Exception\ValidationHttpException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Global exception listener for API endpoints.
 */
#[AsEventListener(event: 'kernel.exception')]
class ApiExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle API requests
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $response = null;

        if ($exception instanceof ValidationHttpException) {
            $response = $this->handleValidationHttpException($exception);
        } elseif ($exception instanceof ValidationException) {
            $response = $this->handleValidationException($exception);
        } elseif ($exception instanceof HttpExceptionInterface) {
            $response = $this->handleHttpException($exception);
        } else {
            $response = $this->handleGenericException($exception);
        }

        if ($response !== null) {
            $event->setResponse($response);
        }
    }

    private function handleValidationHttpException(ValidationHttpException $exception): JsonResponse
    {
        $responseDTO = new class($exception->getViolations()) extends ApiResponseDTO {
            public function __construct(
                private readonly \Symfony\Component\Validator\ConstraintViolationListInterface $violations
            ) {
                parent::__construct(false, 'Validation failed', [(string) $this->violations]);
            }
        };

        return new JsonResponse(
            $responseDTO->toArray(),
            $exception->getStatusCode(),
            $exception->getHeaders()
        );
    }

    private function handleValidationException(ValidationException $exception): JsonResponse
    {
        $responseDTO = new class($exception->getViolations()) extends ApiResponseDTO {
            public function __construct(
                private readonly \Symfony\Component\Validator\ConstraintViolationListInterface $violations
            ) {
                parent::__construct(false, 'Validation failed', [(string) $this->violations]);
            }
        };

        return new JsonResponse($responseDTO->toArray(), Response::HTTP_BAD_REQUEST);
    }

    private function handleHttpException(HttpExceptionInterface $exception): JsonResponse
    {
        $responseDTO = new class($exception->getMessage()) extends ApiResponseDTO {
            public function __construct(string $message)
            {
                parent::__construct(false, $message);
            }
        };

        return new JsonResponse(
            $responseDTO->toArray(),
            $exception->getStatusCode(),
            $exception->getHeaders()
        );
    }

    private function handleGenericException(\Throwable $exception): JsonResponse
    {
        $responseDTO = new class() extends ApiResponseDTO {
            public function __construct()
            {
                parent::__construct(false, 'Internal server error');
            }
        };

        return new JsonResponse($responseDTO->toArray(), Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}