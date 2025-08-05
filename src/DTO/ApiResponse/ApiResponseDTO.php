<?php

declare(strict_types=1);

namespace App\DTO\ApiResponse;

use App\DTO\DTO;

/**
 * Base DTO for standardized API responses.
 */
abstract class ApiResponseDTO implements DTO
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $message = null,
        public readonly ?array $errors = null,
    ) {
    }

    /**
     * Creates a successful response.
     */
    public static function success(string $message, array $data = []): static
    {
        return new static(true, $message, null, $data);
    }

    /**
     * Creates an error response.
     */
    public static function error(string $message, array $errors = []): static
    {
        return new static(false, $message, $errors);
    }

    /**
     * Converts DTO to array for JSON response.
     */
    public function toArray(): array
    {
        $response = [
            'success' => $this->success,
        ];

        if ($this->message !== null) {
            $response['message'] = $this->message;
        }

        if ($this->errors !== null && !empty($this->errors)) {
            $response['error'] = $this->errors;
        }

        return array_merge($response, $this->getAdditionalData());
    }

    /**
     * Override this method to add specific data to the response.
     */
    protected function getAdditionalData(): array
    {
        return [];
    }
}