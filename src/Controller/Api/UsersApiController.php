<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Enum\Role;
use App\Repository\UserRepository;
use DTO\UserDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UsersApiController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TranslatorInterface $translator,
    )
    {
    }

    #[Route('/search', name: 'app_admin_api_users_search', methods: ['GET'])]
    public function searchUsers(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        if (mb_strlen($query) < 2) {
            return $this->json(['error' => 'Query must be at least 2 characters long'], Response::HTTP_BAD_REQUEST);
        }

        $users = $this->userRepository->searchUsers($query);
        
        $results = array_map(fn(User $user) => [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhoneString(),
            'isConfirmed' => $user->isConfirmed(),
            'roles' => $user->getRoles(),
        ], $users);

        return $this->json(['success' => true, 'users' => $results, 'count' => count($results)]);
    }

    #[Route('/{id}/promote', name: 'app_admin_api_users_promote', methods: ['POST'])]
    public function promoteUser(User $user): JsonResponse
    {
        if ($user->isAdmin()) {
            return $this->json(['success' => false, 'message' => 'User is already an admin'], Response::HTTP_BAD_REQUEST);
        }

        $user->addRole(Role::ADMIN);
        $this->userRepository->save($user);

        return $this->json(['success' => true, 'message' => 'User promoted successfully']);
    }

    #[Route('/{id}/demote', name: 'app_admin_api_users_demote', methods: ['POST'])]
    public function demoteUser(User $user): JsonResponse
    {
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            return $this->json(['success' => false, 'message' => 'You cannot remove admin rights from yourself'], Response::HTTP_BAD_REQUEST);
        }

        if (!$user->isAdmin()) {
            return $this->json(['success' => false, 'message' => 'User is not an admin'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepository->countByRole(Role::ADMIN) <= 1) {
            return $this->json(['success' => false, 'message' => 'Cannot remove the last admin'], Response::HTTP_BAD_REQUEST);
        }

        $user->removeRole(Role::ADMIN);
        $this->userRepository->save($user);

        return $this->json(['success' => true, 'message' => 'Admin rights removed successfully']);
    }

    #[Route('/{id}', name: 'app_admin_api_users_delete', methods: ['DELETE'])]
    public function deleteUser(User $user): JsonResponse
    {
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            return $this->json(['error' => 'You cannot delete yourself'], Response::HTTP_BAD_REQUEST);
        }

        if ($user->isAdmin() && $this->userRepository->countByRole(Role::ADMIN) <= 1) {
            return $this->json(['error' => 'Cannot delete the last admin'], Response::HTTP_BAD_REQUEST);
        }

        $this->userRepository->remove($user);

        return $this->json(['success' => true, 'message' => 'User deleted successfully']);
    }

    #[Route('/', name: 'app_admin_api_users_create', methods: ['POST'])]
    public function createUser(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Email and password are required'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepository->findOneBy(['email' => $data['email']])) {
            return $this->json(['error' => 'User with this email already exists'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        $user->setIsConfirmed($data['isConfirmed'] ?? false);

        $roles = [Role::USER];
        if ($data['isAdmin'] ?? false) {
            $roles[] = Role::ADMIN;
        }
        $user->setRoles($roles);

        $this->userRepository->save($user);

        return $this->json(['success' => true, 'message' => 'User created successfully'], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'app_admin_api_users_get', methods: ['GET'])]
    public function getUserById(User $user): JsonResponse
    {
        $dto = UserDTO::fromEntity($user);
        return $this->json($dto);
    }

    #[Route('/{id}', name: 'app_admin_api_users_update', methods: ['PUT'])]
    public function updateUser(User $user, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email'])) {
            return $this->json(['error' => 'Email is required'], Response::HTTP_BAD_REQUEST);
        }

        if ($data['email'] !== $user->getEmail()) {
            if ($this->userRepository->findOneBy(['email' => $data['email']])) {
                return $this->json(['error' => 'User with this email already exists'], Response::HTTP_CONFLICT);
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

        $this->userRepository->flush();

        return $this->json(['success' => true, 'message' => 'User updated successfully']);
    }
}
