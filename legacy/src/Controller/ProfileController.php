<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\Interfaces\OrderRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for managing user profile-related functionalities.
 *
 * This controller provides views for authenticated users to see their
 * personal data, specifically their upcoming and past orders.
 * All routes within this controller are protected by the 'ROLE_USER' security attribute.
 */
#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    /**
     * Displays the orders associated with the currently authenticated user.
     *
     * This method retrieves the authenticated user, then fetches their upcoming
     * and past orders from the order repository. These orders are then passed
     * to the 'profile/orders.html.twig' template for rendering.
     *
     * @param OrderRepositoryInterface $orderRepository The repository for managing Order entities.
     * @return Response The HTTP response for the user's orders page.
     */
    #[Route('/orders', name: 'app_profile_orders')]
    public function orders(OrderRepositoryInterface $orderRepository): Response
    {
        // Get the currently authenticated user
        $user = $this->getUser();
        
        // Find upcoming orders for the current user
        $upcomingOrders = $orderRepository->findUpcomingOrdersByUser($user);
        // Find past orders for the current user
        $pastOrders = $orderRepository->findPastOrdersByUser($user);
        
        // Render the profile orders template with the retrieved data
        return $this->render('profile/orders.html.twig', [
            'upcomingOrders' => $upcomingOrders,
            'pastOrders' => $pastOrders,
        ]);
    }
}