<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Review;
use App\Enum\ReviewStatus;
use App\Repository\ReviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reviews')]
#[IsGranted('ROLE_ADMIN')]
final class ReviewController extends AbstractController
{
    public function __construct(private readonly ReviewRepository $reviews)
    {
    }

    #[Route('', name: 'app_admin_reviews', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $status = ReviewStatus::tryFrom($request->query->getString('status', ReviewStatus::PENDING->value));

        return $this->render('admin/review/index.html.twig', [
            'page' => $this->reviews->findByStatus($status, $request->query->getInt('page', 1)),
            'status' => $status,
            'pending_count' => $this->reviews->countPending(),
        ]);
    }

    #[Route('/{id}/status', name: 'app_admin_review_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function status(Review $review, Request $request): Response
    {
        $status = ReviewStatus::tryFrom($request->getPayload()->getString('status'));
        if (null !== $status && $this->isCsrfTokenValid('review-'.$review->getId(), $request->getPayload()->getString('_token'))) {
            $review->setStatus($status);
            $this->reviews->save($review);
            $this->addFlash('success', 'admin.reviews.flash.updated');
        }

        return $this->redirectToRoute('app_admin_reviews', [
            'status' => $request->getPayload()->getString('back', ReviewStatus::PENDING->value),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_review_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Review $review, Request $request): Response
    {
        if ($this->isCsrfTokenValid('review-'.$review->getId(), $request->getPayload()->getString('_token'))) {
            $this->reviews->remove($review);
            $this->addFlash('success', 'admin.reviews.flash.deleted');
        }

        return $this->redirectToRoute('app_admin_reviews');
    }
}
