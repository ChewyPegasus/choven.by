<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/orders', name: 'app_profile_orders')]
    public function orders(OrderRepository $orderRepository): Response
    {
        $user = $this->getUser();
        
        $upcomingOrders = $orderRepository->findUpcomingOrdersByUser($user);
        $pastOrders = $orderRepository->findPastOrdersByUser($user);
        
        return $this->render('profile/orders.html.twig', [
            'upcomingOrders' => $upcomingOrders,
            'pastOrders' => $pastOrders,
        ]);
    }
}
