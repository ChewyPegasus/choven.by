<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Enum representing different user roles within the application.
 *
 * This enum defines the distinct access levels or responsibilities
 * that can be assigned to a user.
 */
enum Role: string
{
    /**
     * Represents a standard authenticated user.
     * This is typically the default role for new registrations.
     */
    case USER = 'user';

    /**
     * Represents an administrative user with elevated privileges.
     * Users with this role typically have access to administrative functions.
     */
    case ADMIN = 'admin';
}