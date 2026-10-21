<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\UserAdminType;
use App\Repository\UserRepository;
use App\Service\EmailVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Route('', name: 'app_admin_users', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = $request->query->getString('q');
        $role = $request->query->getString('role');

        return $this->render('admin/user/index.html.twig', [
            'page' => $this->users->search($query, $role, $request->query->getInt('page', 1)),
            'query' => $query,
            'role' => $role,
        ]);
    }

    /**
     * Users who agreed to receive news (for the newsletter service).
     */
    #[Route('/subscribers.csv', name: 'app_admin_users_subscribers', methods: ['GET'])]
    public function subscribers(): StreamedResponse
    {
        $users = $this->users->findBy(['marketingConsent' => true, 'isVerified' => true], ['createdAt' => 'ASC']);

        $response = new StreamedResponse(static function () use ($users): void {
            $out = fopen('php://output', 'w');
            if (false === $out) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Email', 'Name', 'Language', 'Registered'], ';', '"', '');
            foreach ($users as $user) {
                fputcsv($out, [$user->getEmail(), $user->getName(), $user->getLocale(), $user->getCreatedAt()->format('Y-m-d')], ';', '"', '');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'subscribers-'.date('Y-m-d').'.csv'));

        return $response;
    }

    #[Route('/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = (new User())->setIsVerified(true)->setLocale($request->getLocale());
        $form = $this->createForm(UserAdminType::class, $user, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($this->hasher->hashPassword($user, (string) $form->get('newPassword')->getData()));
            $this->users->save($user);
            $this->addFlash('success', 'admin.users.flash.created');

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/user/form.html.twig', ['form' => $form, 'user' => $user]);
    }

    #[Route('/{id}/edit', name: 'app_admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request, #[CurrentUser] User $currentUser): Response
    {
        $isSelf = $user->getId() === $currentUser->getId();
        $wasAdmin = $user->isAdmin();

        $form = $this->createForm(UserAdminType::class, $user, ['is_self' => $isSelf]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($wasAdmin && !$user->isAdmin() && $this->users->countAdmins() <= 1) {
                $user->setAdmin(true);
                $this->addFlash('error', 'admin.users.flash.last_admin');
            }

            $password = $form->get('newPassword')->getData();
            if (\is_string($password) && '' !== $password) {
                $user->setPassword($this->hasher->hashPassword($user, $password));
            }

            $this->users->save($user);
            $this->addFlash('success', 'admin.users.flash.saved');

            return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
        }

        return $this->render('admin/user/form.html.twig', ['form' => $form, 'user' => $user]);
    }

    #[Route('/{id}/resend-verification', name: 'app_admin_user_resend', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resendVerification(User $user, Request $request, EmailVerifier $verifier): Response
    {
        if ($this->isCsrfTokenValid('resend-'.$user->getId(), $request->getPayload()->getString('_token')) && !$user->isVerified()) {
            $verifier->sendConfirmation($user);
            $this->addFlash('success', 'admin.users.flash.verification_sent');
        }

        return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_admin_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(User $user, Request $request, #[CurrentUser] User $currentUser): Response
    {
        if (!$this->isCsrfTokenValid('delete-user-'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');
        } elseif ($user->getId() === $currentUser->getId()) {
            $this->addFlash('error', 'admin.users.flash.cannot_delete_self');
        } elseif ($user->isAdmin() && $this->users->countAdmins() <= 1) {
            $this->addFlash('error', 'admin.users.flash.last_admin');
        } else {
            $this->users->remove($user);
            $this->addFlash('success', 'admin.users.flash.deleted');
        }

        return $this->redirectToRoute('app_admin_users');
    }
}
