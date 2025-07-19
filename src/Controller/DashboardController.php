<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\FailedEmail;
use App\Entity\User;
use App\Enum\Role;
use App\Repository\FailedEmailRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly FailedEmailRepository $failedEmailRepository,
        private readonly UserRepository $userRepository,
        private readonly OrderRepository $orderRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    )
    {
    }

    private function getRoutesDirectory(): string
    {
        return (string) $this->getParameter('kernel.project_dir') . '/assets/data/routes/';
    }

    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(): Response
    {
        // Stats for main admin page
        $stats = [
            'total_users' => $this->userRepository->count([]),
            'total_orders' => $this->orderRepository->count([]),
            'failed_emails' => $this->failedEmailRepository->count([]),
            'confirmed_users' => $this->userRepository->count(['isConfirmed' => true]),
            'pending_users' => $this->userRepository->count(['isConfirmed' => false]),
        ];

        return $this->render('dashboard/index.html.twig', [
            'stats' => $stats,
        ]);
    }

    #[Route('/failed-emails', name: 'app_admin_failed_emails')]
    public function failedEmails(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $totalEmails = $this->failedEmailRepository->count([]);
        $totalPages = ceil($totalEmails / $limit);
        
        $failedEmails = $this->failedEmailRepository->findBy(
            [], 
            ['createdAt' => 'DESC'], 
            $limit, 
            $offset
        );
        
        return $this->render('dashboard/failed_emails.html.twig', [
            'failedEmails' => $failedEmails,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }
    
    #[Route('/failed-emails/{id}', name: 'app_admin_failed_email_details')]
    public function failedEmailDetails(FailedEmail $failedEmail): Response
    {
        return $this->render('dashboard/failed_email_details.html.twig', [
            'email' => $failedEmail,
        ]);
    }

    #[Route('/orders', name: 'app_admin_orders')]
    public function orders(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $totalOrders = $this->orderRepository->count([]);
        $totalPages = ceil($totalOrders / $limit);
        
        $orders = $this->orderRepository->findBy(
            [], 
            ['startDate' => 'DESC'], 
            $limit, 
            $offset
        );
        
        return $this->render('dashboard/orders.html.twig', [
            'orders' => $orders,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $totalUsers = $this->userRepository->count([]);
        $totalPages = ceil($totalUsers / $limit);
        
        $users = $this->userRepository->findBy(
            [], 
            ['id' => 'DESC'],
            $limit, 
            $offset
        );
        
        return $this->render('dashboard/users.html.twig', [
            'users' => $users,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/logs', name: 'app_admin_logs')]
    public function logs(Request $request): Response
    {
        $logFile = (string) $this->getParameter('kernel.project_dir') . '/var/log/dev.log';
        $lines = max(50, min(1000, $request->query->getInt('lines', 100)));
        
        $logContent = '';
        if (file_exists($logFile)) {
            $logContent = $this->getTailLines($logFile, $lines);
        }
        
        return $this->render('dashboard/logs.html.twig', [
            'logContent' => $logContent,
            'lines' => $lines,
            'logExists' => file_exists($logFile),
        ]);
    }

    #[Route('/make-admin', name: 'app_admin_make_admin')]
    public function makeAdmin(Request $request): Response
    {
        $admins = $this->userRepository->findByRole(Role::ADMIN);
        
        $regularUsers = $this->userRepository->findUsersWithoutRole(Role::ADMIN);
        
        if ($request->isXmlHttpRequest() && $request->isMethod('POST')) {
            $userId = $request->request->getInt('userId');
            $user = $this->userRepository->find($userId);
            
            if (!$user) {
                return $this->json(['success' => false, 'message' => 'User not found'], 404);
            }
            
            if ($user->isAdmin()) {
                return $this->json(['success' => false, 'message' => 'User is already an admin'], 400);
            }
            
            $user->addRole(Role::ADMIN);
            $this->entityManager->flush();
            
            return $this->json([
                'success' => true, 
                'message' => 'User promoted to admin successfully',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail()
                ]
            ]);
        }
        
        return $this->render('dashboard/make_admin.html.twig', [
            'admins' => $admins,
            'regularUsers' => $regularUsers,
        ]);
    }

    #[Route('/make-admin/remove/{id}', name: 'app_admin_remove_admin', methods: ['POST'])]
    public function removeAdmin(User $user, Request $request): Response
    {
        if (!$request->isXmlHttpRequest()) {
            throw $this->createAccessDeniedException();
        }
        
        if (!$user->isAdmin()) {
            return $this->json(['success' => false, 'message' => 'User is not an admin'], 400);
        }
        
        // not the last admin
        $adminCount = $this->userRepository->countByRole(Role::ADMIN);
        if ($adminCount <= 1) {
            return $this->json(['success' => false, 'message' => 'Cannot remove the last admin'], 400);
        }
        
        $user->removeRole(Role::ADMIN);
        $this->entityManager->flush();
        
        return $this->json([
            'success' => true, 
            'message' => 'Admin rights removed successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail()
            ]
        ]);
    }

    #[Route('/routes', name: 'app_admin_routes')]
    public function routes(): Response
    {
        $routesDirectory = $this->getRoutesDirectory();
        $routes = [];
        
        if (is_dir($routesDirectory)) {
            $files = glob($routesDirectory . '*.json')? : [];
            
            foreach ($files as $file) {
                $routeId = basename($file, '.json');
                $routes[] = [
                    'id' => $routeId,
                    'size' => filesize($file),
                    'modified' => new \DateTime('@' . filemtime($file))
                ];
            }
        }
        
        return $this->render('dashboard/routes.html.twig', [
            'routes' => $routes
        ]);
    }

    #[Route('/routes/api/{routeId}', name: 'app_admin_routes_api_get', methods: ['GET'])]
    public function getRoute(string $routeId): JsonResponse
    {
        $filePath = $this->getRoutesDirectory() . $routeId . '.json';
        
        if (!file_exists($filePath)) {
            return $this->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        $content = file_get_contents($filePath);
        if (!$content) {
            return $this->json(['error' => 'Route content not found'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $routeData = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Invalid JSON in route file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($routeData);
    }

    #[Route('/routes/api', name: 'app_admin_routes_api_create', methods: ['POST'])]
    public function createRoute(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['id']) || !isset($data['data'])) {
            return $this->json(['error' => 'Invalid request data'], Response::HTTP_BAD_REQUEST);
        }

        $routeId = $data['id'];
        $routeData = $data['data'];

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $routeId)) {
            return $this->json(['error' => 'Invalid route ID format'], Response::HTTP_BAD_REQUEST);
        }

        $routesDirectory = $this->getRoutesDirectory();
        $filePath = $routesDirectory . $routeId . '.json';
        
        if (file_exists($filePath)) {
            return $this->json(['error' => 'Route already exists'], Response::HTTP_CONFLICT);
        }

        if (!is_dir($routesDirectory)) {
            mkdir($routesDirectory, 0755, true);
        }

        $jsonContent = json_encode($routeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($filePath, $jsonContent) === false) {
            return $this->json(['error' => 'Failed to save route'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => true, 'message' => 'Route created successfully']);
    }

    #[Route('/routes/api/{routeId}', name: 'app_admin_routes_api_update', methods: ['PUT'])]
    public function updateRoute(string $routeId, Request $request): JsonResponse
    {
        $filePath = $this->getRoutesDirectory() . $routeId . '.json';
        
        if (!file_exists($filePath)) {
            return $this->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        $content = $request->getContent();
        $routeData = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
        }

        $jsonContent = json_encode($routeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($filePath, $jsonContent) === false) {
            return $this->json(['error' => 'Failed to update route'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => true, 'message' => 'Route updated successfully']);
    }

    #[Route('/routes/api/{routeId}', name: 'app_admin_routes_api_delete', methods: ['DELETE'])]
    public function deleteRoute(string $routeId): JsonResponse
    {
        $filePath = $this->getRoutesDirectory() . $routeId . '.json';
        
        if (!file_exists($filePath)) {
            return $this->json(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        if (!unlink($filePath)) {
            return $this->json(['error' => 'Failed to delete route'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => true, 'message' => 'Route deleted successfully']);
    }

    #[Route('/users/api/{id}', name: 'app_admin_users_api_get', methods: ['GET'])]
    public function getUserById(User $user): JsonResponse
    {
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhoneString(),
            'isConfirmed' => $user->isConfirmed(),
            'roles' => $user->getRoles(),
            'confirmationCode' => $user->getConfirmationCode(),
        ]);
    }

    #[Route('/users/api', name: 'app_admin_users_api_create', methods: ['POST'])]
    public function createUser(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['email']) || !isset($data['password'])) {
            return $this->json(['error' => 'Email and password are required'], Response::HTTP_BAD_REQUEST);
        }

        // check existence
        $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return $this->json(['error' => 'User with this email already exists'], Response::HTTP_CONFLICT);
        }

        try {
            $user = new User();
            $user->setEmail($data['email']);
            
            // set phone unless set
            if (!empty($data['phone'])) {
                try {
                    $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
                    $phoneNumber = $phoneUtil->parse($data['phone'], 'BY');
                    if ($phoneUtil->isValidNumber($phoneNumber)) {
                        $user->setPhone($phoneNumber);
                    } else {
                        return $this->json(['error' => 'Invalid phone number'], Response::HTTP_BAD_REQUEST);
                    }
                } catch (\Exception $e) {
                    return $this->json(['error' => 'Invalid phone number format'], Response::HTTP_BAD_REQUEST);
                }
            }
            
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);

            $isConfirmed = $data['isConfirmed'] ?? false;
            $user->setIsConfirmed($isConfirmed);
            
            // Используем enum вместо строк
            $roles = [Role::USER];
            if ($data['isAdmin'] ?? false) {
                $roles[] = Role::ADMIN;
            }
            $user->setRoles($roles);

            if (!$user->isConfirmed()) {
                $user->setConfirmationCode(bin2hex(random_bytes(10)));
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $this->json([
                'success' => true, 
                'message' => 'User created successfully',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail()
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to create user: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/users/api/{id}', name: 'app_admin_users_api_update', methods: ['PUT'])]
    public function updateUser(User $user, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['email'])) {
            return $this->json(['error' => 'Email is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // check if email is unique (if it changed)
            if ($data['email'] !== $user->getEmail()) {
                $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    return $this->json(['error' => 'User with this email already exists'], Response::HTTP_CONFLICT);
                }
                $user->setEmail($data['email']);
            }
            
            // refresh phone
            if (isset($data['phone'])) {
                if (!empty($data['phone'])) {
                    try {
                        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
                        $phoneNumber = $phoneUtil->parse($data['phone'], 'BY');
                        if ($phoneUtil->isValidNumber($phoneNumber)) {
                            $user->setPhone($phoneNumber);
                        } else {
                            return $this->json(['error' => 'Invalid phone number'], Response::HTTP_BAD_REQUEST);
                        }
                    } catch (\Exception $e) {
                        return $this->json(['error' => 'Invalid phone number format'], Response::HTTP_BAD_REQUEST);
                    }
                } else {
                    $user->setPhone(null);
                }
            }
            
            // refresh password
            if (!empty($data['password'])) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
                $user->setPassword($hashedPassword);
            }
            
            $wasConfirmed = $user->isConfirmed();
            $isConfirmed = $data['isConfirmed'] ?? false;
            $user->setIsConfirmed($isConfirmed);
            
            if (!$wasConfirmed && $isConfirmed) {
                $user->setConfirmationCode(null);
            } elseif ($wasConfirmed && !$isConfirmed) {
                $user->setConfirmationCode(bin2hex(random_bytes(10)));
            }
            
            // Используем enum вместо строк
            $roles = [Role::USER];
            if ($data['isAdmin'] ?? false) {
                $roles[] = Role::ADMIN;
            }
            $user->setRoles($roles);

            $this->entityManager->flush();

            return $this->json([
                'success' => true, 
                'message' => 'User updated successfully',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'isConfirmed' => $user->isConfirmed(),
                    'roles' => $user->getRoles()
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to update user: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/users/api/{id}', name: 'app_admin_users_api_delete', methods: ['DELETE'])]
    public function deleteUser(User $user): JsonResponse
    {
        try {
            if ($user->isAdmin()) {
                $adminCount = $this->userRepository->countByRole(Role::ADMIN);
                if ($adminCount <= 1) {
                    return $this->json(['error' => 'Cannot delete the last admin'], Response::HTTP_BAD_REQUEST);
                }
            }

            $currentUser = $this->getUser();
            if ($currentUser instanceof User && $user->getId() === $currentUser->getId()) {
                return $this->json(['error' => 'You cannot delete yourself'], Response::HTTP_BAD_REQUEST);
            }

            $email = $user->getEmail();
            
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            return $this->json([
                'success' => true, 
                'message' => 'User deleted successfully',
                'deletedUser' => [
                    'id' => $user->getId(),
                    'email' => $email
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to delete user: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/users/api/{userId}/toggle-confirmation', name: 'app_admin_users_api_toggle_confirmation', methods: ['POST'])]
    public function toggleUserConfirmation(int $userId): JsonResponse
    {
        $user = $this->userRepository->find($userId);
        
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $wasConfirmed = $user->isConfirmed();
            $user->setIsConfirmed(!$wasConfirmed);
            
            if ($user->isConfirmed()) {
                $user->setConfirmationCode(null);
            } else {
                $user->setConfirmationCode(bin2hex(random_bytes(10)));
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => $user->isConfirmed() ? 'User confirmed successfully' : 'User confirmation revoked',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'isConfirmed' => $user->isConfirmed(),
                    'confirmationCode' => $user->getConfirmationCode()
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to toggle confirmation: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/users/api/{userId}/toggle-admin', name: 'app_admin_users_api_toggle_admin', methods: ['POST'])]
    public function toggleUserAdmin(int $userId): JsonResponse
    {
        $user = $this->userRepository->find($userId);
        
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $isAdmin = $user->isAdmin();
            
            if ($isAdmin) {
                $adminCount = $this->userRepository->countByRole(Role::ADMIN);
                if ($adminCount <= 1) {
                    return $this->json(['error' => 'Cannot remove admin rights from the last admin'], Response::HTTP_BAD_REQUEST);
                }
                
                $user->removeRole(Role::ADMIN);
            } else {
                $user->addRole(Role::ADMIN);
            }
            
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => $isAdmin ? 'Admin rights removed successfully' : 'Admin rights granted successfully',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                    'isAdmin' => $user->isAdmin()
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to toggle admin rights: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/users/api/bulk-action', name: 'app_admin_users_api_bulk_action', methods: ['POST'])]
    public function bulkUserAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['action']) || !isset($data['userIds']) || !is_array($data['userIds'])) {
            return $this->json(['error' => 'Invalid request data'], Response::HTTP_BAD_REQUEST);
        }

        $action = $data['action'];
        $userIds = array_filter($data['userIds'], 'is_numeric');
        
        if (empty($userIds)) {
            return $this->json(['error' => 'No valid user IDs provided'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $users = $this->userRepository->findBy(['id' => $userIds]);
            $processedCount = 0;
            $errors = [];
            
            switch ($action) {
                case 'confirm':
                    foreach ($users as $user) {
                        $user->setIsConfirmed(true);
                        $user->setConfirmationCode(null);
                        $processedCount++;
                    }
                    break;
                    
                case 'unconfirm':
                    foreach ($users as $user) {
                        $user->setIsConfirmed(false);
                        $user->setConfirmationCode(bin2hex(random_bytes(10)));
                        $processedCount++;
                    }
                    break;
                    
                case 'make_admin':
                    foreach ($users as $user) {
                        if (!$user->isAdmin()) {
                            $user->addRole(Role::ADMIN);
                            $processedCount++;
                        }
                    }
                    break;
                    
                case 'remove_admin':
                    $adminCount = $this->userRepository->countByRole(Role::ADMIN);
                    $adminUsersToProcess = array_filter($users, fn($user) => $user->isAdmin());
                    
                    if ($adminCount - count($adminUsersToProcess) < 1) {
                        return $this->json(['error' => 'Cannot remove all admins'], Response::HTTP_BAD_REQUEST);
                    }
                    
                    foreach ($users as $user) {
                        if ($user->isAdmin()) {
                            $user->removeRole(Role::ADMIN);
                            $processedCount++;
                        }
                    }
                    break;
                    
                case 'delete':
                    $currentUser = $this->getUser();
                    $currentUserId = ($currentUser instanceof User) ? $currentUser->getId() : null;
                    $adminCount = $this->userRepository->countByRole(Role::ADMIN);
                    $adminUsersToDelete = array_filter($users, fn($user) => $user->isAdmin());
                    
                    if ($adminCount - count($adminUsersToDelete) < 1) {
                        return $this->json(['error' => 'Cannot delete all admins'], Response::HTTP_BAD_REQUEST);
                    }
                    
                    foreach ($users as $user) {
                        if ($user->getId() === $currentUserId) {
                            $errors[] = "Cannot delete yourself (ID: {$user->getId()})";
                            continue;
                        }
                        
                        $this->entityManager->remove($user);
                        $processedCount++;
                    }
                    break;
                    
                default:
                    return $this->json(['error' => 'Invalid action'], Response::HTTP_BAD_REQUEST);
            }
            
            $this->entityManager->flush();
            
            $response = [
                'success' => true,
                'message' => "Successfully processed {$processedCount} user(s)",
                'processedCount' => $processedCount,
                'totalRequested' => count($userIds)
            ];
            
            if (!empty($errors)) {
                $response['errors'] = $errors;
            }
            
            return $this->json($response);
            
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to perform bulk action: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/users/api/search', name: 'app_admin_users_api_search', methods: ['GET'])]
    public function searchUsers(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = min(50, max(1, $request->query->getInt('limit', 10)));
        
        if (strlen($query) < 2) {
            return $this->json(['error' => 'Search query must be at least 2 characters'], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            $users = $this->userRepository->searchUsers($query, $limit);
            
            $results = array_map(function ($user) {
                return [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'phone' => $user->getPhoneString(),
                    'isConfirmed' => $user->isConfirmed(),
                    'roles' => $user->getRoles(),
                    'ordersCount' => $user->getOrders()->count(),
                ];
            }, $users);
            
            return $this->json([
                'success' => true,
                'users' => $results,
                'count' => count($results),
                'query' => $query
            ]);
            
        } catch (\Exception $e) {
            return $this->json(['error' => 'Search failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getTailLines(string $filename, int $lines): string
    {
        $file = new \SplFileObject($filename, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        
        $startLine = max(0, $lastLine - $lines);
        $file->seek($startLine);
        
        $content = '';
        while (!$file->eof()) {
            $content .= $file->fgets();
        }
        
        return $content;
    }
}
