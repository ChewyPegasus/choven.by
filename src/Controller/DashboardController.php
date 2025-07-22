<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\CacheableTrait;
use App\Entity\FailedEmail;
use App\Repository\FailedEmailRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Enum\Role;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    use CacheableTrait;

    public function __construct(
        private readonly FailedEmailRepository $failedEmailRepository,
        private readonly UserRepository $userRepository,
        private readonly OrderRepository $orderRepository
    ) {
    }

    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(): Response
    {
        $stats = [
            'total_users' => $this->userRepository->count([]),
            'total_orders' => $this->orderRepository->count([]),
            'failed_emails' => $this->failedEmailRepository->count([]),
            'confirmed_users' => $this->userRepository->count(['isConfirmed' => true]),
        ];

        return $this->createCacheableResponse('dashboard/index.html.twig', [
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
        
        $failedEmails = $this->failedEmailRepository->findBy([], ['createdAt' => 'DESC'], $limit, $offset);
        
        return $this->createCacheableResponse('dashboard/failed_emails.html.twig', [
            'failedEmails' => $failedEmails,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }
    
    #[Route('/failed-emails/{id}', name: 'app_admin_failed_email_details')]
    public function failedEmailDetails(FailedEmail $failedEmail): Response
    {
        return $this->createCacheableResponse('dashboard/failed_email_details.html.twig', [
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
        
        $orders = $this->orderRepository->findBy([], ['startDate' => 'DESC'], $limit, $offset);
        
        return $this->createCacheableResponse('dashboard/orders.html.twig', [
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
        
        $users = $this->userRepository->findBy([], ['id' => 'DESC'], $limit, $offset);
        
        return $this->createCacheableResponse('dashboard/users.html.twig', [
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
        
        return $this->createCacheableResponse('dashboard/logs.html.twig', [
            'logContent' => $logContent,
            'lines' => $lines,
            'logExists' => file_exists($logFile),
        ]);
    }

    #[Route('/make-admin', name: 'app_admin_make_admin')]
    public function makeAdmin(): Response
    {
        $admins = $this->userRepository->findByRole(Role::ADMIN);
        $regularUsers = $this->userRepository->findUsersWithoutRole(Role::ADMIN);
        
        return $this->createCacheableResponse('dashboard/make_admin.html.twig', [
            'admins' => $admins,
            'regularUsers' => $regularUsers,
        ]);
    }

    #[Route('/routes', name: 'app_admin_routes')]
    public function routes(): Response
    {
        $routesDirectory = (string) $this->getParameter('kernel.project_dir') . '/assets/data/routes/';
        $routes = [];
        
        if (is_dir($routesDirectory)) {
            $files = glob($routesDirectory . '*.json') ?: [];
            foreach ($files as $file) {
                $routes[] = [
                    'id' => basename($file, '.json'),
                    'size' => filesize($file),
                    'modified' => (new \DateTime())->setTimestamp(filemtime($file))
                ];
            }
        }
        
        return $this->createCacheableResponse('dashboard/routes.html.twig', ['routes' => $routes]);
    }

    private function getTailLines(string $filename, int $lines): string
    {
        try {
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
        } catch (\Exception $e) {
            return "Error reading log file: " . $e->getMessage();
        }
    }
}