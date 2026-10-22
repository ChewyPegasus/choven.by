<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\FaqItemRepository;
use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Blog articles and the FAQ page.
 */
final class BlogController extends AbstractController
{
    #[Route('/blog', name: 'app_blog', methods: ['GET'])]
    public function index(Request $request, PostRepository $posts): Response
    {
        return $this->render('blog/index.html.twig', [
            'page' => $posts->findPublishedPage($request->query->getInt('page', 1)),
        ]);
    }

    #[Route('/blog/{slug}', name: 'app_blog_post', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function post(string $slug, PostRepository $posts): Response
    {
        $post = $posts->findOnePublishedBySlug($slug) ?? throw $this->createNotFoundException();

        return $this->render('blog/post.html.twig', [
            'post' => $post,
            'more' => $posts->findLatest(3, $post),
        ]);
    }

    #[Route('/faq', name: 'app_faq', methods: ['GET'])]
    public function faq(FaqItemRepository $faq): Response
    {
        return $this->render('faq/index.html.twig', ['items' => $faq->findActive()]);
    }
}
