<?php

declare(strict_types=1);

namespace App\Controller\Profile;

use App\Entity\Order;
use App\Entity\User;
use App\Form\NewPasswordType;
use App\Form\ProfileType;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Security\Voter\OrderVoter;
use App\Service\BookingPolicy;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_profile_orders');
    }

    #[Route('/orders', name: 'app_profile_orders', methods: ['GET'])]
    public function orders(#[CurrentUser] User $user, OrderRepository $orders, BookingPolicy $policy): Response
    {
        return $this->render('profile/orders.html.twig', [
            'upcoming' => $orders->findUpcomingForUser($user),
            'past' => $orders->findPastForUser($user),
            'policy' => $policy,
        ]);
    }

    #[Route('/orders/{reference}/cancel', name: 'app_profile_order_cancel', methods: ['POST'])]
    public function cancel(
        string $reference,
        Request $request,
        OrderRepository $orders,
        BookingService $bookingService,
    ): Response {
        $order = $orders->findOneByReference($reference);
        if (!$order instanceof Order) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(OrderVoter::CANCEL, $order);

        if ($this->isCsrfTokenValid('cancel-order-'.$order->getId(), $request->getPayload()->getString('_token'))) {
            $bookingService->cancelByCustomer($order);
            $this->addFlash('success', 'booking.flash.cancelled');
        } else {
            $this->addFlash('error', 'flash.invalid_csrf');
        }

        return $this->redirectToRoute('app_profile_orders');
    }

    #[Route('/settings', name: 'app_profile_settings', methods: ['GET', 'POST'])]
    public function settings(
        #[CurrentUser] User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $profileForm = $this->createForm(ProfileType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'profile.flash.saved');

            return $this->redirectToRoute('app_profile_settings', ['_locale' => $user->getLocale()]);
        }
        if ($profileForm->isSubmitted()) {
            // Do not keep invalid changes on the managed entity.
            $entityManager->refresh($user);
        }

        $passwordForm = $this->createForm(NewPasswordType::class, null, ['require_current' => true]);
        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $user->setPassword($hasher->hashPassword($user, (string) $passwordForm->get('plainPassword')->getData()));
            $entityManager->flush();
            $this->addFlash('success', 'profile.flash.password_changed');

            return $this->redirectToRoute('app_profile_settings');
        }

        return $this->render('profile/settings.html.twig', [
            'profile_form' => $profileForm,
            'password_form' => $passwordForm,
        ]);
    }

    /**
     * Removes the account; bookings are kept for the team (they are detached from the user).
     */
    #[Route('/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function delete(#[CurrentUser] User $user, Request $request, UserRepository $users, Security $security): Response
    {
        if (!$this->isCsrfTokenValid('delete-account', $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');

            return $this->redirectToRoute('app_profile_settings');
        }

        if ($user->isAdmin() && $users->countAdmins() <= 1) {
            $this->addFlash('error', 'profile.flash.last_admin');

            return $this->redirectToRoute('app_profile_settings');
        }

        $security->logout(false);
        $users->remove($user);
        $this->addFlash('success', 'profile.flash.deleted');

        return $this->redirectToRoute('app_main');
    }
}
