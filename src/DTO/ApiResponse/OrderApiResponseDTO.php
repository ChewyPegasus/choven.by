<?php

declare(strict_types=1);

namespace App\DTO\ApiResponse;

/**
 * DTO for Order API responses.
 */
class OrderApiResponseDTO extends ApiResponseDTO
{
    public function __construct(
        bool $success,
        ?string $message = null,
        ?array $errors = null,
        public readonly ?array $order = null,
        public readonly ?array $orders = null,
    ) {
        parent::__construct($success, $message, $errors);
    }

    public static function successWithOrder(string $message, array $order): self
    {
        return new self(true, $message, null, $order);
    }

    public static function successWithOrders(string $message, array $orders): self
    {
        return new self(true, $message, null, null, $orders);
    }

    protected function getAdditionalData(): array
    {
        $data = [];
        
        if ($this->order !== null) {
            $data['order'] = $this->order;
        }
        
        if ($this->orders !== null) {
            $data['orders'] = $this->orders;
        }
        
        return $data;
    }
}