<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\ApiResponse\UserApiResponseDTO;
use App\Entity\User;
use App\Enum\Role;
use App\DTO\User\UserDTO;
use App\Exception\UserNotFoundException;
use App\Factory\UserFactory;
use App\Repository\Interfaces\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
    /**
     * Constructs a new UsersApiController instance.
     *
     * @param UserRepositoryInterface $userRepository The repository for managing User entities.
     * @param UserPasswordHasherInterface $passwordHasher The service for hashing user passwords.
     * @param TranslatorInterface $translator The translator service for internationalization.
     * @param UserFactory $userFactory The factory for creating User entities.
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TranslatorInterface $translator,
        private readonly UserFactory $userFactory,
    ) {
    }

    /**
     * Searches for users based on a query string.
     *
     * Requires a query parameter 'q' with a minimum length of 2 characters.
     * Returns a list of users matching the query, including their ID, email,
     * phone number, confirmation status, and roles.
     *
     * @param Request $request The HTTP request containing the search query.
     * @return JsonResponse A JSON response with search results or an error.
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
     *
     * Adds the 'ROLE_ADMIN' to the specified user's roles.
     * Returns an error if the user is already an admin.
     *
     * @param User $user The User entity to promote, resolved by the route parameter.
     * @return JsonResponse A JSON response indicating success or failure.
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
     *
     * Removes the 'ROLE_ADMIN' from the specified user's roles.
     * Prevents an admin from demoting themselves or if it's the last admin.
     *
     * @param User $user The User entity to demote, resolved by the route parameter.
     * @return JsonResponse A JSON response indicating success or failure.
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
     *
     * Prevents an admin from deleting their own account or the last admin account.
     *
     * @param User $user The User entity to delete, resolved by the route parameter.
     * @return JsonResponse A JSON response indicating success or failure.
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
     *
     * Requires 'email' and 'password' in the request payload.
     * Hashes the password and allows setting 'isConfirmed' and 'isAdmin' flags.
     * Returns an error if a user with the given email already exists.
     *
     * @param Request $request The HTTP request containing the new user data.
     * @return JsonResponse A JSON response indicating success or failure.
     */
    #[Route('/', name: 'app_admin_api_users_create', methods: ['POST'])]
    public function createUser(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password'])) {
            $response = UserApiResponseDTO::error('Email and password are required');
            return $this->json($response->toArray(), Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->userRepository->findOneByEmail($data['email']);
            $response = UserApiResponseDTO::error('User with this email already exists');
            return $this->json($response->toArray(), Response::HTTP_CONFLICT);
        } catch (UserNotFoundException) {
            // User doesn't exist, continue
        }

        $user = $this->userFactory->create();
        $user->setEmail($data['email']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        $user->setIsConfirmed($data['isConfirmed'] ?? false);

        $roles = [Role::USER];
        if ($data['isAdmin'] ?? false) {
            $roles[] = Role::ADMIN;
        }
        $user->setRoles($roles);

        $this->userRepository->save($user);

        $response = UserApiResponseDTO::success('User created successfully');
        return $this->json($response->toArray(), Response::HTTP_CREATED);
    }

    /**
     * Retrieves a single user by their ID.
     *
     * Returns a UserDTO representation of the user.
     *
     * @param User $user The User entity to retrieve, resolved by the route parameter.
     * @return JsonResponse A JSON response containing the user's data.
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
     *
     * Allows updating email, password, confirmation status, and roles.
     * Returns an error if the new email already exists for another user.
     *
     * @param User $user The User entity to update, resolved by the route parameter.
     * @param Request $request The HTTP request containing the updated user data.
     * @return JsonResponse A JSON response indicating success or failure.
     */
    #[Route('/{id}', name: 'app_admin_api_users_update', methods: ['PUT'])]
    public function updateUser(User $user, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email'])) {
            $response = UserApiResponseDTO::error('Email is required');
            return $this->json($response->toArray(), Response::HTTP_BAD_REQUEST);
        }

        if ($data['email'] !== $user->getEmail()) {
            if ($this->userRepository->findOneBy(['email' => $data['email']])) {
                $response = UserApiResponseDTO::error('User with this email already exists');
                return $this->json($response->toArray(), Response::HTTP_CONFLICT);
            }
            $user->setEmail($data['email']);
        }

        if (!empty($data['password'])) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        }

        $user->setIsConfirmed($data['isConfirmed'] ?? false);

        $roles = [Role::USER];
        if ($data['isAdmin'] ?? false) {
            $roles[] = Role::ADMIN;
        }
        $user->setRoles($roles);

        $this->userRepository->save($user);

        $response = UserApiResponseDTO::success('User updated successfully');
        return $this->json($response->toArray());
    }
}