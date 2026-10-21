<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\RaftingRoute;
use App\Form\Admin\RaftingRouteType;
use App\Repository\RaftingRouteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Routes drawn on the landing page map.
 */
#[Route('/admin/routes')]
#[IsGranted('ROLE_ADMIN')]
final class RouteController extends AbstractController
{
    public function __construct(private readonly RaftingRouteRepository $routes)
    {
    }

    #[Route('', name: 'app_admin_routes', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/route/index.html.twig', ['routes' => $this->routes->findAllOrdered()]);
    }

    #[Route('/new', name: 'app_admin_route_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $route = (new RaftingRoute())
            ->setPosition($this->routes->getNextPosition())
            ->setPoints([
                ['lat' => 53.9, 'lng' => 27.56, 'type' => 'start', 'name' => [], 'description' => []],
                ['lat' => 53.95, 'lng' => 27.6, 'type' => 'end', 'name' => [], 'description' => []],
            ]);

        return $this->handleForm($route, $request, 'admin.routes.flash.created');
    }

    #[Route('/{id}/edit', name: 'app_admin_route_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(RaftingRoute $route, Request $request): Response
    {
        return $this->handleForm($route, $request, 'admin.routes.flash.saved');
    }

    #[Route('/{id}/delete', name: 'app_admin_route_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(RaftingRoute $route, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete-route-'.$route->getId(), $request->getPayload()->getString('_token'))) {
            $this->routes->remove($route);
            $this->addFlash('success', 'admin.routes.flash.deleted');
        }

        return $this->redirectToRoute('app_admin_routes');
    }

    private function handleForm(RaftingRoute $route, Request $request, string $successMessage): Response
    {
        $form = $this->createForm(RaftingRouteType::class, $route);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->routes->save($route);
            $this->addFlash('success', $successMessage);

            return $this->redirectToRoute('app_admin_route_edit', ['id' => $route->getId()]);
        }

        return $this->render('admin/route/form.html.twig', [
            'form' => $form,
            'route' => $route,
        ]);
    }
}
