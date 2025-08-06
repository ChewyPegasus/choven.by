<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\ApiResponse\UserApiResponseDTO;
use App\DTO\User\CreateUserDTO;
use App\DTO\User\UpdateUserDTO;
use App\DTO\User\UserDTO;
use App\Entity\User;
use App\Enum\Role;
use App\Exception\ValidationException;
use App\Exception\ValidationHttpException;
use App\Factory\UserFactory;
use App\Repository\Interfaces\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * API controller for managing user accounts, accessible by administrators.
 *
 * This controller provides endpoints for searching, promoting, demoting,
 * deleting, creating, retrieving, and updating user entities.
 * All actions in this controller require the authenticated user to have the 'ROLE_ADMIN' role.
 */
#[Route('/api/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UsersApiController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TranslatorInterface $translator,
        private readonly UserFactory $userFactory,
    ) {
    }

    /**
     * Searches for users based on a query string.
     */
    #[Route('/search', name: 'app_admin_api_users_search', methods: ['GET'])]
    public function searchUsers(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        if (mb_strlen($query) < 2) {
            $response = UserApiResponseDTO::error('Query must be at least 2 characters long');
            return $this->json($response->toArray(), Response::HTTP_BAD_REQUEST);
        }

        $users = $this->userRepository->searchUsers($query);
        
        $results = array_map(fn(User $user) => [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhoneString(),
            'isConfirmed' => $user->isConfirmed(),
            'roles' => $user->getRoles(),
        ], $users);

        $response = UserApiResponseDTO::successWithUsers('Users found', $results);
        return $this->json($response->toArray());
    }

    /**
     * Promotes a user to an administrator.
     */
    #[Route('/{id}/promote', name: 'app_admin_api_users_promote', methods: ['POST'])]
    public function promoteUser(User $user): JsonResponse
    {
        if ($user->isAdmin()) {
            $response = UserApiResponseDTO::error('User is already an admin');
            return $this->json($response->toArray(), Response::HTTP_BAD_REQUEST);
        }

        $user->addRole(Role::ADMIN);
        $this->userRepository->save($user);

        $response = UserApiResponseDTO::success('User promoted successfully');
        return $this->json($response->toArray());
    }

    /**
     * Demotes a user from an administrator.
     */
    #[Route('/{id}/demote', name: 'app_admin_api_users_demote', methods: ['POST'])]
    public function demoteUser(User $user): JsonResponse
    {
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            $response = UserApiResponseDTO::error('You cannot remove admin rights from yourself');
            return $this->json($response->toArray(), Response::HTTP_BAD_REQUEST);
        }

        if (!$user->isAdmin()) {
            $response = UserApiResponseDTO::error('User is not an admin');
            return $this->json($response->toArray(), Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepository->countByRole(Role::ADMIN) <= 1) {
            $response = UserApiResponseDTO::error('Cannot remove the last admin');
            return $this->json($response->toArray(), Response::HTTP_BAD_REQUEST);
        }

        $user->removeRole(Role::ADMIN);
        $this->userRepository->save($user);

        $response = UserApiResponseDTO::success('Admin rights removed successfully');
        return $this->json($response->toArray());
    }

    /**
     * Deletes a user account.
     */
    #[Route('/{id}', name: 'app_admin_api_users_delete', methods: ['DELETE'])]
    public function deleteUser(User $user): JsonResponse
    {
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            $response = UserApiResponseDTO::error('You cannot delete yourself');
            return $this->json($response->toArray(), Response::HTTP_BAD_REQUEST);
        }

        if ($user->isAdmin() && $this->userRepository->countByRole(Role::ADMIN) <= 1) {
            $response = UserApiResponseDTO::error('Cannot delete the last admin');
            return $this->json($response->toArray(), Response::HTTP_BAD_REQUEST);
        }

        $this->userRepository->remove($user);

        $response = UserApiResponseDTO::success('User deleted successfully');
        return $this->json($response->toArray());
    }

    /**
     * Creates a new user account.
     */
    #[Route('/', name: 'app_admin_api_users_create', methods: ['POST'])]
    public function createUser(
        #[MapRequestPayload] CreateUserDTO $dto,
    ): JsonResponse {
        try {
            $user = $this->userFactory->createFromCreateDTO($dto);
            $this->userRepository->save($user);

            $response = UserApiResponseDTO::success('User created successfully');
            return $this->json($response->toArray(), Response::HTTP_CREATED);
        } catch (ValidationException $e) {
            throw new ValidationHttpException($e->getViolations(), 'Validation failed');
        }
    }

    /**
     * Retrieves a single user by their ID.
     */
    #[Route('/{id}', name: 'app_admin_api_users_get', methods: ['GET'])]
    public function getUserById(User $user): JsonResponse
    {
        $dto = UserDTO::fromEntity($user);
        $response = UserApiResponseDTO::successWithUser('User retrieved successfully', $dto);
        return $this->json($response->toArray());
    }

    /**
     * Updates an existing user account.
     */
    #[Route('/{id}', name: 'app_admin_api_users_update', methods: ['PUT'])]
    public function updateUser(
        User $user,
        #[MapRequestPayload] UpdateUserDTO $dto,
    ): JsonResponse {
        try {
            $updatedUser = $this->userFactory->updateFromUpdateDTO($user, $dto);
            $this->userRepository->save($updatedUser);

            $response = UserApiResponseDTO::success('User updated successfully');
            return $this->json($response->toArray());
        } catch (ValidationException $e) {
            throw new ValidationHttpException($e->getViolations(), 'Validation failed');
        }
    }
}