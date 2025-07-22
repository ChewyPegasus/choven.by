<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\Role;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminApiController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/users/search', name: 'app_admin_api_users_search', methods: ['GET'])]
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

    #[Route('/users/{id}/promote', name: 'app_admin_api_users_promote', methods: ['POST'])]
    public function promoteUser(User $user): JsonResponse
    {
        if ($user->isAdmin()) {
            return $this->json(['success' => false, 'message' => 'User is already an admin'], Response::HTTP_BAD_REQUEST);
        }

        $user->addRole(Role::ADMIN);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'message' => 'User promoted successfully']);
    }

    #[Route('/users/{id}/demote', name: 'app_admin_api_users_demote', methods: ['POST'])]
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
        $this->entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Admin rights removed successfully']);
    }

    #[Route('/users/{id}', name: 'app_admin_api_users_delete', methods: ['DELETE'])]
    public function deleteUser(User $user): JsonResponse
    {
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            return $this->json(['error' => 'You cannot delete yourself'], Response::HTTP_BAD_REQUEST);
        }

        if ($user->isAdmin() && $this->userRepository->countByRole(Role::ADMIN) <= 1) {
            return $this->json(['error' => 'Cannot delete the last admin'], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'message' => 'User deleted successfully']);
    }

    private function getRoutesDirectory(): string
    {
        return $this->getParameter('kernel.project_dir') . '/assets/data/routes/';
    }

    #[Route('/routes/{routeId}', name: 'app_admin_api_routes_get', methods: ['GET'])]
    public function getRoute(string $routeId): JsonResponse
    {
        $filePath = $this->getRoutesDirectory() . $routeId . '.json';
        
        if (!file_exists($filePath)) {
            return $this->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        $content = file_get_contents($filePath);
        $routeData = json_decode((string)$content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Invalid JSON in route file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($routeData);
    }

    #[Route('/routes', name: 'app_admin_api_routes_create', methods: ['POST'])]
    public function createRoute(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['id']) || !isset($data['data'])) {
            return $this->json(['error' => 'Invalid request data. "id" and "data" are required.'], Response::HTTP_BAD_REQUEST);
        }

        $routeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['id']);
        $routeData = $data['data'];

        if (empty($routeId)) {
            return $this->json(['error' => 'Invalid route ID format'], Response::HTTP_BAD_REQUEST);
        }

        $filePath = $this->getRoutesDirectory() . $routeId . '.json';
        
        if (file_exists($filePath)) {
            return $this->json(['error' => 'Route with this ID already exists'], Response::HTTP_CONFLICT);
        }

        $jsonContent = json_encode($routeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($filePath, $jsonContent) === false) {
            return $this->json(['error' => 'Failed to save route file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => true, 'message' => 'Route created successfully'], Response::HTTP_CREATED);
    }

    #[Route('/routes/{routeId}', name: 'app_admin_api_routes_update', methods: ['PUT'])]
    public function updateRoute(string $routeId, Request $request): JsonResponse
    {
        $filePath = $this->getRoutesDirectory() . $routeId . '.json';
        
        if (!file_exists($filePath)) {
            return $this->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        $content = $request->getContent();
        json_decode($content);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Invalid JSON data provided'], Response::HTTP_BAD_REQUEST);
        }

        $prettyJson = json_encode(json_decode($content, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($filePath, $prettyJson) === false) {
            return $this->json(['error' => 'Failed to update route file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => true, 'message' => 'Route updated successfully']);
    }

    #[Route('/routes/{routeId}', name: 'app_admin_api_routes_delete', methods: ['DELETE'])]
    public function deleteRoute(string $routeId): JsonResponse
    {
        $filePath = $this->getRoutesDirectory() . $routeId . '.json';
        
        if (!file_exists($filePath)) {
            return $this->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        if (!unlink($filePath)) {
            return $this->json(['error' => 'Failed to delete route file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => true, 'message' => 'Route deleted successfully']);
    }

    #[Route('/users', name: 'app_admin_api_users_create', methods: ['POST'])]
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

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'message' => 'User created successfully'], Response::HTTP_CREATED);
    }

    #[Route('/users/{id}', name: 'app_admin_api_users_get', methods: ['GET'])]
    public function getUserById(User $user): JsonResponse
    {
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhoneString(),
            'isConfirmed' => $user->isConfirmed(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/users/{id}', name: 'app_admin_api_users_update', methods: ['PUT'])]
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

        $this->entityManager->flush();

        return $this->json(['success' => true, 'message' => 'User updated successfully']);
    }
}
