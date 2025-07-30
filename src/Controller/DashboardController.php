<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\CacheableTrait;
use App\Entity\FailedEmail;
use App\Enum\Package;
use App\Enum\River;
use App\Repository\Interfaces\UserRepositoryInterface;
use App\Enum\Role;
use App\Repository\Interfaces\FailedEmailRepositoryInterface;
use App\Repository\Interfaces\OrderRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for the administration dashboard.
 *
 * This controller provides various views and data for administrators,
 * including statistics, lists of failed emails, orders, users, and logs.
 * All routes within this controller are protected by the 'ROLE_ADMIN' security attribute.
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    use CacheableTrait;

    /**
     * Constructs a new DashboardController instance.
     *
     * @param FailedEmailRepositoryInterface $failedEmailRepository The repository for managing failed email entities.
     * @param UserRepositoryInterface $userRepository The repository for managing user entities.
     * @param OrderRepositoryInterface $orderRepository The repository for managing order entities.
     */
    public function __construct(
        private readonly FailedEmailRepositoryInterface $failedEmailRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly OrderRepositoryInterface $orderRepository
    ) {
    }

    /**
     * Displays the main dashboard overview with key statistics.
     *
     * Retrieves counts for total users, total orders, failed emails, and confirmed users,
     * then renders the dashboard index page with these statistics.
     * The response is configured to be cacheable.
     *
     * @return Response The HTTP response for the dashboard index page.
     */
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

    /**
     * Displays a paginated list of failed emails.
     *
     * Retrieves failed email records from the repository, ordered by creation date,
     * and calculates pagination details.
     * The response is configured to be cacheable.
     *
     * @param Request $request The current HTTP request, used to get the page number.
     * @return Response The HTTP response for the failed emails list page.
     */
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
    
    /**
     * Displays the details of a single failed email.
     *
     * @param FailedEmail $failedEmail The FailedEmail entity resolved by the route parameter.
     * @return Response The HTTP response for the failed email details page.
     */
    #[Route('/failed-emails/{id}', name: 'app_admin_failed_email_details')]
    public function failedEmailDetails(FailedEmail $failedEmail): Response
    {
        return $this->createCacheableResponse('dashboard/failed_email_details.html.twig', [
            'email' => $failedEmail,
        ]);
    }

    /**
     * Displays a paginated list of orders.
     *
     * Retrieves order records from the repository, ordered by start date,
     * and calculates pagination details. Also provides lists of all available
     * rivers and packages for filtering or display purposes.
     * The response is configured to be cacheable.
     *
     * @param Request $request The current HTTP request, used to get the page number.
     * @return Response The HTTP response for the orders list page.
     */
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
            'rivers' => River::cases(),
            'packages' => Package::cases(),
        ]);
    }

    /**
     * Displays a paginated list of users.
     *
     * Retrieves user records from the repository, ordered by ID,
     * and calculates pagination details.
     * The response is configured to be cacheable.
     *
     * @param Request $request The current HTTP request, used to get the page number.
     * @return Response The HTTP response for the users list page.
     */
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

    /**
     * Displays the content of the development log file.
     *
     * Reads a specified number of lines from the end of the 'dev.log' file
     * located in the 'var/log' directory.
     * The number of lines can be controlled by the 'lines' query parameter.
     * The response is configured to be cacheable.
     *
     * @param Request $request The current HTTP request, used to get the number of lines.
     * @return Response The HTTP response for the logs display page.
     */
    #[Route('/logs', name: 'app_admin_logs')]
    public function logs(Request $request): Response
    {
        $logFile = (string) $this->getParameter('kernel.project_dir') . '/var/log/dev.log';
        // Ensure lines is between 50 and 1000
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

    /**
     * Displays the page for managing administrator roles.
     *
     * Retrieves lists of current administrators and regular users (those without the admin role)
     * to facilitate promoting or demoting users.
     * The response is configured to be cacheable.
     *
     * @return Response The HTTP response for the make admin page.
     */
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

    /**
     * Displays a list of available route JSON files.
     *
     * Scans the 'assets/data/routes/' directory for JSON files and provides
     * their IDs, sizes, and last modification dates.
     * The response is configured to be cacheable.
     *
     * @return Response The HTTP response for the routes list page.
     */
    #[Route('/routes', name: 'app_admin_routes')]
    public function routes(): Response
    {
        $routesDirectory = (string) $this->getParameter('kernel.project_dir') . '/assets/data/routes/';
        $routes = [];
        
        if (is_dir($routesDirectory)) {
            // Get all JSON files in the directory, suppressing errors if glob fails
            $files = glob($routesDirectory . '*.json') ?: [];
            foreach ($files as $file) {
                $routes[] = [
                    'id' => basename($file, '.json'), // Extract ID from filename
                    'size' => filesize($file), // Get file size
                    'modified' => (new \DateTime())->setTimestamp(filemtime($file)) // Get last modification time
                ];
            }
        }
        
        return $this->createCacheableResponse('dashboard/routes.html.twig', ['routes' => $routes]);
    }

    /**
     * Reads the last N lines of a given file.
     *
     * This helper method uses SplFileObject to efficiently read a specified
     * number of lines from the end of a file.
     *
     * @param string $filename The path to the file to read.
     * @param int $lines The number of lines to read from the end of the file.
     * @return string The content of the last N lines, or an error message if the file cannot be read.
     */
    private function getTailLines(string $filename, int $lines): string
    {
        try {
            $file = new \SplFileObject($filename, 'r');
            $file->seek(PHP_INT_MAX); // Move cursor to the end of the file
            $lastLine = $file->key(); // Get the last line number
            $startLine = max(0, $lastLine - $lines); // Calculate the starting line for tail
            $file->seek($startLine); // Move cursor to the calculated start line
            
            $content = '';
            while (!$file->eof()) {
                $content .= $file->fgets(); // Read line by line
            }
            return $content;
        } catch (\Exception $e) {
            return "Error reading log file: " . $e->getMessage();
        }
    }
}