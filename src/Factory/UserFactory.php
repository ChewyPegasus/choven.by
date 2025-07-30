<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;

/**
 * Factory for creating User entities.
 *
 * This factory provides a simple method to instantiate new `User` objects,
 * ensuring consistency in how user entities are created throughout the application.
 */
class UserFactory
{
    /**
     * Creates a new User entity instance.
     *
     * @return User A newly created User entity.
     */
    public function create(): User
    {
        return new User();
    }
}