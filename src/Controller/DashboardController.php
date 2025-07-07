<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\FailedEmail;
use App\Repository\FailedEmailRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly FailedEmailRepository $failedEmailRepository
    ) {
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
}
