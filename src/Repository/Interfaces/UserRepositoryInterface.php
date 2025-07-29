<?php

declare(strict_types=1);

namespace App\Repository\Interfaces;

use App\Entity\User;
use App\Enum\Role;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

interface UserRepositoryInterface
{   
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void;

    /**
     * @return User[]
     */
    public function findByRole(Role $role): array;

    public function countByRole(Role $role): int;

    /**
     * @return User[]
     */
    public function findUsersWithoutRole(Role $role): array;

    /**
     * @return User[]
     */
    public function searchUsers(string $query, int $limit = 10): array;

    public function save($user): void;

    public function remove($user): void;

    public function flush(): void;
}