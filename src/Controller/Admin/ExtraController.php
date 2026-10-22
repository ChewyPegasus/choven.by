<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Extra;
use App\Form\Admin\ExtraType;
use App\Repository\ExtraRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/extras')]
#[IsGranted('ROLE_ADMIN')]
final class ExtraController extends AbstractController
{
    public function __construct(private readonly ExtraRepository $extras)
    {
    }

    #[Route('', name: 'app_admin_extras', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/extra/index.html.twig', ['extras' => $this->extras->findAllOrdered()]);
    }

    #[Route('/new', name: 'app_admin_extra_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return $this->handleForm((new Extra())->setPosition($this->extras->getNextPosition()), $request, 'admin.extras.flash.created');
    }

    #[Route('/{id}/edit', name: 'app_admin_extra_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Extra $extra, Request $request): Response
    {
        return $this->handleForm($extra, $request, 'admin.extras.flash.saved');
    }

    #[Route('/{id}/toggle', name: 'app_admin_extra_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Extra $extra, Request $request): Response
    {
        if ($this->isCsrfTokenValid('toggle-extra-'.$extra->getId(), $request->getPayload()->getString('_token'))) {
            $extra->setIsActive(!$extra->isActive());
            $this->extras->save($extra);
        }

        return $this->redirectToRoute('app_admin_extras');
    }

    #[Route('/{id}/delete', name: 'app_admin_extra_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Extra $extra, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete-extra-'.$extra->getId(), $request->getPayload()->getString('_token'))) {
            // Bookings keep their copy of the name and price.
            $this->extras->remove($extra);
            $this->addFlash('success', 'admin.extras.flash.deleted');
        }

        return $this->redirectToRoute('app_admin_extras');
    }

    private function handleForm(Extra $extra, Request $request, string $successMessage): Response
    {
        $form = $this->createForm(ExtraType::class, $extra);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->extras->save($extra);
            $this->addFlash('success', $successMessage);

            return $this->redirectToRoute('app_admin_extras');
        }

        return $this->render('admin/extra/form.html.twig', ['form' => $form, 'extra' => $extra]);
    }
}
