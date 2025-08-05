<?php

declare(strict_types=1);

namespace App\DTO\ApiResponse;

use App\DTO\User\UserDTO;

/**
 * DTO for User API responses.
 */
class UserApiResponseDTO extends ApiResponseDTO
{
    public function __construct(
        bool $success,
        ?string $message = null,
        ?array $errors = null,
        public readonly ?UserDTO $user = null,
        public readonly ?array $users = null,
        public readonly ?int $count = null,
    ) {
        parent::__construct($success, $message, $errors);
    }

    public static function successWithUser(string $message, UserDTO $user): self
    {
        return new self(true, $message, null, $user);
    }

    public static function successWithUsers(string $message, array $users, int $count = null): self
    {
        return new self(true, $message, null, null, $users, $count ?? count($users));
    }

    protected function getAdditionalData(): array
    {
        $data = [];
        
        if ($this->user !== null) {
            $data = array_merge($data, $this->user->toArray());
        }
        
        if ($this->users !== null) {
            $data['users'] = $this->users;
            $data['count'] = $this->count;
        }
        
        return $data;
    }
}