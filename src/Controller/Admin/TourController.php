<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tour;
use App\Form\Admin\TourType;
use App\Repository\TourRepository;
use App\Service\ImageUploader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tours')]
#[IsGranted('ROLE_ADMIN')]
final class TourController extends AbstractController
{
    public function __construct(
        private readonly TourRepository $tours,
        private readonly ImageUploader $uploader,
    ) {
    }

    #[Route('', name: 'app_admin_tours', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/tour/index.html.twig', ['tours' => $this->tours->findAllOrdered()]);
    }

    #[Route('/new', name: 'app_admin_tour_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $tour = (new Tour())->setPosition($this->tours->getNextPosition());

        return $this->handleForm($tour, $request, 'admin.tours.flash.created');
    }

    #[Route('/{id}/edit', name: 'app_admin_tour_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Tour $tour, Request $request): Response
    {
        return $this->handleForm($tour, $request, 'admin.tours.flash.saved');
    }

    #[Route('/{id}/toggle', name: 'app_admin_tour_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Tour $tour, Request $request): Response
    {
        if ($this->isCsrfTokenValid('toggle-tour-'.$tour->getId(), $request->getPayload()->getString('_token'))) {
            $tour->setIsActive(!$tour->isActive());
            $this->tours->save($tour);
        }

        return $this->redirectToRoute('app_admin_tours');
    }

    #[Route('/{id}/delete', name: 'app_admin_tour_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Tour $tour, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete-tour-'.$tour->getId(), $request->getPayload()->getString('_token'))) {
            $this->uploader->remove($tour->getImage());
            $this->tours->remove($tour);
            $this->addFlash('success', 'admin.tours.flash.deleted');
        }

        return $this->redirectToRoute('app_admin_tours');
    }

    private function handleForm(Tour $tour, Request $request, string $successMessage): Response
    {
        $form = $this->createForm(TourType::class, $tour);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImage($tour, $form);
            $this->tours->save($tour);
            $this->addFlash('success', $successMessage);

            return $this->redirectToRoute('app_admin_tours');
        }

        return $this->render('admin/tour/form.html.twig', ['form' => $form, 'tour' => $tour]);
    }

    /**
     * @param FormInterface<Tour> $form
     */
    private function handleImage(Tour $tour, FormInterface $form): void
    {
        $file = $form->get('imageFile')->getData();
        if (!$file instanceof UploadedFile) {
            return;
        }

        $previous = $tour->getImage();
        $tour->setImage($this->uploader->upload($file, 'tours'));
        $this->uploader->remove($previous);
    }
}
