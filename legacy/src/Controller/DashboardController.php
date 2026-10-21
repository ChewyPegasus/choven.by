<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\CacheableTrait;
use App\DTO\Dashboard\DashboardStatsDTO;
use App\DTO\Dashboard\LogViewDTO;
use App\DTO\Dashboard\PaginationDTO;
use App\DTO\Dashboard\RouteFileDTO;
use App\Entity\FailedEmail;
use App\Enum\Package;
use App\Enum\River;
use App\Enum\Role;
use App\Repository\Interfaces\FailedEmailRepositoryInterface;
use App\Repository\Interfaces\OrderRepositoryInterface;
use App\Repository\Interfaces\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for the administration dashboard.
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    use CacheableTrait;

    public function __construct(
        private readonly FailedEmailRepositoryInterface $failedEmailRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly OrderRepositoryInterface $orderRepository
    ) {
    }

    /**
     * Displays the main dashboard overview with key statistics.
     */
    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(): Response
    {
        $statsDTO = new DashboardStatsDTO(
            totalUsers: $this->userRepository->count([]),
            totalOrders: $this->orderRepository->count([]),
            failedEmails: $this->failedEmailRepository->count([]),
            confirmedUsers: $this->userRepository->count(['isConfirmed' => true]),
        );

        return $this->createCacheableResponse('dashboard/index.html.twig', [
            'stats' => $statsDTO->toArray(),
            'statsDto' => $statsDTO, // Для дополнительных методов в шаблоне
        ]);
    }

    /**
     * Displays a paginated list of failed emails.
     */
    #[Route('/failed-emails', name: 'app_admin_failed_emails')]
    public function failedEmails(Request $request): Response
    {
        $totalEmails = $this->failedEmailRepository->count([]);
        $paginationDTO = PaginationDTO::fromRequest(
            $request->query->getInt('page', 1),
            $totalEmails,
            10
        );
        
        $failedEmails = $this->failedEmailRepository->findWithPagination(
            $paginationDTO->itemsPerPage,
            $paginationDTO->offset
        );
        
        return $this->createCacheableResponse('dashboard/failed_emails.html.twig', [
            'failedEmails' => $failedEmails,
            'pagination' => $paginationDTO->toArray(),
        ]);
    }

    /**
     * Displays a paginated list of orders.
     */
    #[Route('/orders', name: 'app_admin_orders')]
    public function orders(Request $request): Response
    {
        $totalOrders = $this->orderRepository->count([]);
        $paginationDTO = PaginationDTO::fromRequest(
            $request->query->getInt('page', 1),
            $totalOrders,
            20
        );
        
        $orders = $this->orderRepository->findWithPagination(
            $paginationDTO->itemsPerPage,
            $paginationDTO->offset
        );
        
        return $this->createCacheableResponse('dashboard/orders.html.twig', [
            'orders' => $orders,
            'pagination' => $paginationDTO->toArray(),
            'rivers' => River::cases(),
            'packages' => Package::cases(),
        ]);
    }

    /**
     * Displays a paginated list of users.
     */
    #[Route('/users', name: 'app_admin_users')]
    public function users(Request $request): Response
    {
        $totalUsers = $this->userRepository->count([]);
        $paginationDTO = PaginationDTO::fromRequest(
            $request->query->getInt('page', 1),
            $totalUsers,
            20
        );
        
        $users = $this->userRepository->findWithPagination(
            $paginationDTO->itemsPerPage,
            $paginationDTO->offset
        );
        
        return $this->createCacheableResponse('dashboard/users.html.twig', [
            'users' => $users,
            'pagination' => $paginationDTO->toArray(),
        ]);
    }

    /**
     * Displays the content of the development log file.
     */
    #[Route('/logs', name: 'app_admin_logs')]
    public function logs(Request $request): Response
    {
        $logFile = $this->getParameter('kernel.project_dir') . '/var/log/dev.log';
        $requestedLines = $request->query->getInt('lines', 100);
        $lines = LogViewDTO::validateLines($requestedLines);
        
        $logContent = '';
        $fileExists = file_exists($logFile);
        
        if ($fileExists) {
            $logContent = $this->getTailLines($logFile, $lines);
        }
        
        $logViewDTO = new LogViewDTO($logContent, $lines, $fileExists, $logFile);
        
        return $this->createCacheableResponse('dashboard/logs.html.twig', $logViewDTO->toArray());
    }

    /**
     * Displays a list of available route JSON files.
     */
    #[Route('/routes', name: 'app_admin_routes')]
    public function routes(): Response
    {
        $routesDirectory = $this->getParameter('kernel.project_dir') . '/assets/data/routes/';
        $routeFiles = [];
        
        if (is_dir($routesDirectory)) {
            $files = glob($routesDirectory . '*.json') ?: [];
            foreach ($files as $file) {
                $routeFiles[] = new RouteFileDTO(
                    id: basename($file, '.json'),
                    size: filesize($file) ?: 0,
                    modified: (new \DateTime())->setTimestamp(filemtime($file) ?: 0)
                );
            }
        }
        
        return $this->createCacheableResponse('dashboard/routes.html.twig', [
            'routes' => array_map(fn(RouteFileDTO $dto) => $dto->toArray(), $routeFiles),
            'routeDTOs' => $routeFiles, // Для дополнительных методов в шаблоне
        ]);
    }

    // Остальные методы остаются без изменений...
    #[Route('/failed-emails/{id}', name: 'app_admin_failed_email_details')]
    public function failedEmailDetails(FailedEmail $failedEmail): Response
    {
        return $this->createCacheableResponse('dashboard/failed_email_details.html.twig', [
            'email' => $failedEmail,
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