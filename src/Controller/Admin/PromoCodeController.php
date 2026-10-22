<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\PromoCode;
use App\Form\Admin\PromoCodeType;
use App\Repository\PromoCodeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/promo-codes')]
#[IsGranted('ROLE_ADMIN')]
final class PromoCodeController extends AbstractController
{
    public function __construct(private readonly PromoCodeRepository $promoCodes)
    {
    }

    #[Route('', name: 'app_admin_promo_codes', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/promo/index.html.twig', ['codes' => $this->promoCodes->findAllOrdered()]);
    }

    #[Route('/new', name: 'app_admin_promo_code_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return $this->handleForm(new PromoCode(), $request, 'admin.promo.flash.created');
    }

    #[Route('/{id}/edit', name: 'app_admin_promo_code_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(PromoCode $code, Request $request): Response
    {
        return $this->handleForm($code, $request, 'admin.promo.flash.saved');
    }

    #[Route('/{id}/delete', name: 'app_admin_promo_code_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(PromoCode $code, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete-promo-'.$code->getId(), $request->getPayload()->getString('_token'))) {
            $this->promoCodes->remove($code);
            $this->addFlash('success', 'admin.promo.flash.deleted');
        }

        return $this->redirectToRoute('app_admin_promo_codes');
    }

    private function handleForm(PromoCode $code, Request $request, string $successMessage): Response
    {
        $form = $this->createForm(PromoCodeType::class, $code);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->promoCodes->save($code);
            $this->addFlash('success', $successMessage);

            return $this->redirectToRoute('app_admin_promo_codes');
        }

        return $this->render('admin/promo/form.html.twig', ['form' => $form, 'code' => $code]);
    }
}
